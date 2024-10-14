<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$openai = OpenAI::client($_ENV['API_KEY']);

$assistantId = $_ENV['ASSISTANTS_ID'];
$intentos = 0;


const CONSULTAR_PROVEEDORES = [
    'type' => 'function',
    'function' => [
        'name' => 'consultar_proveedores',
        'description' => 'Consulta proveedores filtrando por categoría, costo, ubicación, y servicio.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'categoria' => [
                    'type' => 'string',
                    'description' => 'La categoría de los proveedores, por ejemplo: eventos, música, catering, dj, Viaje de novios, Belleza Novias, Vídeo.',
                ],
                'costo' => [
                    'type' => 'number',
                    'description' => 'El costo máximo que el cliente está dispuesto a pagar.',
                ],
                'ubicacion' => [
                    'type' => 'string',
                    'description' => 'La ubicación donde se requiere el servicio. Por ejemplo: Barcelona, Madrid, Sevilla.',
                ],
                'servicio' => [
                    'type' => 'string',
                    'description' => 'Palabra clave del servicio que se busca, por ejemplo: DJ, fotógrafo. Puede estar vacío.',
                ],
                'limite' => [
                    'type' => 'integer',
                    'description' => 'Número de proveedores a devolver por página.',
                ],
                'pagina' => [
                    'type' => 'integer',
                    'description' => 'Página actual para la paginación.',
                ],
            ],
            'required' => ['categoria', 'costo', 'ubicacion']
        ]
    ]
];

const LISTAR_CATEGORIAS = [
    'type' => 'function',
    'function' => [
        'name' => 'listar_categorias_proveedores',
        'description' => 'Lista todas las categorías de proveedores disponibles.',
        'parameters' => [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ]
    ]
];

const MAS_INFORMACION = [
    'type' => 'function',
    'function' => [
        'name' => 'mas_informacion_proveedor',
        'description' => 'Obtiene más información sobre un proveedor específico, incluyendo su descripción, valoraciones y otros detalles.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'El ID del proveedor del cual se desea obtener más información.',
                ]
            ],
            'required' => ['id']
        ]
    ]
];


function connectToDatabase()
{
    try {
        $dsn = "mysql:host=" . $_ENV['HOST'] . ";dbname=" . $_ENV['DATABASE'];
        $pdo = new PDO($dsn, $_ENV['USER'], $_ENV['PASSWORD']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo "Error de conexión a la base de datos: " . $e->getMessage();
        exit;
    }
}

function consultarProveedores($pdo, $categoria, $costo, $ubicacion, $servicio = "", $limite = 5, $pagina = 1)
{
    $sql = "
        SELECT * 
        FROM proveedores 
        WHERE 
            categoria LIKE :categoria 
            AND CAST(REGEXP_REPLACE(costo, '[^0-9.]', '') AS DECIMAL) <= :costo 
            AND ubicacion LIKE :ubicacion
    ";

    if (!empty($servicio)) {
        $sql .= " AND (informacion LIKE :servicio 
                OR mas_informacion LIKE :servicio 
                OR datos_interes LIKE :servicio)";
    }

    $sql .= " LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    // Calcular el offset para paginación
    $offset = ($pagina - 1) * $limite;

    $categoria = "%$categoria%";
    $ubicacion = "%$ubicacion%";
    $servicio = "%$servicio%";

    // Vincular parámetros
    $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindParam(':costo', $costo, PDO::PARAM_STR);
    $stmt->bindParam(':ubicacion', $ubicacion, PDO::PARAM_STR);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if (!empty($servicio)) {
        $stmt->bindParam(':servicio', $servicio, PDO::PARAM_STR);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function masInformacionProveedores($pdo, $id)
{
    $sql = "SELECT * FROM proveedores WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Consulta categorías disponibles
function consultarCategorias($pdo)
{
    $sql = "SELECT categoria FROM categorias_proveedores";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createThread($openai)
{
    return $openai->threads()->create([]);
}

function addMessage($openai, $threadId, $message)
{
    return $openai->threads()->messages()->create($threadId, [
        'role' => 'user',
        'content' => $message,
    ]);
}

function runAssistant($openai, $threadId)
{

    return $openai->threads()->runs()->create($threadId, [
        'assistant_id' => $_ENV['ASSISTANTS_ID'],
    ]);
}

function checkingStatus($openai, $threadId, $runId)
{
    $runObject = $openai->threads()->runs()->retrieve($threadId, $runId);
    $status = $runObject->status;

    if ($status === 'completed') {
        $messagesList = $openai->threads()->messages()->list($threadId);
        $messages = $messagesList->data;

        if (!empty($messages)) {
            return $messages[0]->content[0]['text'];
        }
    } elseif ($status === 'requires_action') {
        $requiredAction = $runObject->required_action;

        if ($requiredAction->type === 'submit_tool_outputs') {
            foreach ($requiredAction->submit_tool_outputs->tool_calls as $tool_call) {
                if ($tool_call->function->name === "listar_categorias_proveedores") {
                    $pdo = connectToDatabase();
                    $categoriasData = consultarCategorias($pdo);
                    $openai->beta->threads()->runs()->submitToolOutputs($threadId, $runId, [
                        'tool_outputs' => [
                            [
                                'tool_call_id' => $tool_call->id,
                                'output' => json_encode($categoriasData),
                            ],
                        ],
                    ]);
                } elseif ($tool_call->function->name === "consultar_proveedores") {
                    $pdo = connectToDatabase();
                    $args = json_decode($tool_call->function->arguments, true);
                    $categoriasData = consultarProveedores($pdo, $args['categoria'], $args['costo'], $args['ubicacion'], $args['servicio']);
                    $openai->beta->threads()->runs()->submitToolOutputs($threadId, $runId, [
                        'tool_outputs' => [
                            [
                                'tool_call_id' => $tool_call->id,
                                'output' => json_encode($categoriasData),
                            ],
                        ],
                    ]);
                } elseif ($tool_call->function->name === "mas_informacion_proveedor") {
                    $pdo = connectToDatabase();
                    $args = json_decode($tool_call->function->arguments, true);
                    $proveedorData = masInformacionProveedores($pdo, $args['id']);
                    $openai->beta->threads()->runs()->submitToolOutputs($threadId, $runId, [
                        'tool_outputs' => [
                            [
                                'tool_call_id' => $tool_call->id,
                                'output' => json_encode($proveedorData),
                            ],
                        ],
                    ]);
                }
            }
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data['action'] === 'message') {
        $pdo = connectToDatabase();

        $message = $data['message'];
        $threadId = $data['threadId'];

        addMessage($openai, $threadId, $message);
        $runResponse = runAssistant($openai, $threadId);
        $runId = $runResponse->id;

        sleep(10);
        $response = checkingStatus($openai, $threadId, $runId);

        header('Content-Type: application/json');
        echo json_encode(['response' => $response]);
        die();
    }
} elseif ($_GET['action'] === 'thread') {
    $thread = createThread($openai);
    header('Content-Type: application/json');
    echo json_encode(['threadId' => $thread->id]);
    die();
}
