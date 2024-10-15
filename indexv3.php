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
        error_log("Conexión a la base de datos exitosa.");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        exit;
    }
}

function consultarProveedores($pdo, $categoria, $costo, $ubicacion, $servicio = "", $limite = 5, $pagina = 1)
{
    error_log("Iniciando consulta de proveedores con los parámetros: categoría=$categoria, costo=$costo, ubicación=$ubicacion, servicio=$servicio");

    $sql = "
        SELECT id, nombre, categoria, costo, ubicacion
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

    $offset = ($pagina - 1) * $limite;
    $categoria = "%$categoria%";
    $ubicacion = "%$ubicacion%";
    $servicio = "%$servicio%";

    $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindParam(':costo', $costo, PDO::PARAM_STR);
    $stmt->bindParam(':ubicacion', $ubicacion, PDO::PARAM_STR);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if (!empty($servicio)) {
        $stmt->bindParam(':servicio', $servicio, PDO::PARAM_STR);
    }

    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log del número de registros devueltos
    $count = count($resultados);
    error_log("Consulta completada. Número de proveedores devueltos: $count.");

    return $resultados;
}

function masInformacionProveedores($pdo, $id)
{
    error_log("Consultando información del proveedor con ID $id.");

    $sql = "SELECT * FROM proveedores WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Consulta de proveedor $id completada.");
    return $resultado;
}

function consultarCategorias($pdo)
{
    error_log("Consultando categorías de proveedores.");

    $sql = "SELECT categoria FROM categorias_proveedores";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Consulta de categorías completada. Número de categorías devueltas: " . count($categorias));

    return $categorias;
}

function createThread($openai)
{
    error_log("Creando un nuevo hilo para la interacción.");
    return $openai->threads()->create([]);
}

function addMessage($openai, $threadId, $message)
{
    error_log("Añadiendo mensaje al hilo $threadId. Mensaje: $message");
    return $openai->threads()->messages()->create($threadId, [
        'role' => 'user',
        'content' => $message,
    ]);
}

function runAssistant($openai, $threadId)
{
    error_log("Ejecutando el asistente en el hilo $threadId.");
    return $openai->threads()->runs()->create($threadId, [
        'assistant_id' => $_ENV['ASSISTANTS_ID'],
    ]);
}

function checkingStatus($openai, $threadId, $runId)
{
    error_log("Verificando el estado del hilo $threadId y ejecución $runId.");

    while (true) {
        $runObject = $openai->threads()->runs()->retrieve($threadId, $runId);
        $status = $runObject->status;

        error_log("Estado actual de la ejecución: $status");

        if ($status === 'completed') {
            error_log("Ejecución completada para el hilo $threadId.");
            $messagesList = $openai->threads()->messages()->list($threadId);
            $messages = $messagesList->data;

            if (!empty($messages)) {
                return $messages[0]->content[0]['text'];
            } else {
                return 'No hay mensajes disponibles.';
            }
        } elseif ($status === 'requires_action') {
            error_log("La ejecución requiere acción adicional.");

            $requiredAction = $runObject->requiredAction;
            if ($requiredAction->type === 'submit_tool_outputs') {
                foreach ($requiredAction->submitToolOutputs->toolCalls as $tool_call) {
                    error_log("Procesando función: " . $tool_call->function->name);

                    if ($tool_call->function->name === "listar_categorias_proveedores") {
                        $pdo = connectToDatabase();
                        $categoriasData = consultarCategorias($pdo);
                        error_log("Enviando las categorías al asistente.");
                        $openai->threads()->runs()->submitToolOutputs($threadId, $runId, [
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
                        error_log("Enviando proveedores filtrados al asistente.");
                        $openai->threads()->runs()->submitToolOutputs($threadId, $runId, [
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
                        error_log("Enviando información adicional del proveedor al asistente.");
                        $openai->threads()->runs()->submitToolOutputs($threadId, $runId, [
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
        sleep(3);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data['action'] === 'message') {
        $message = $data['message'];
        $threadId = $data['threadId'];

        addMessage($openai, $threadId, $message);
        $runResponse = runAssistant($openai, $threadId);
        $runId = $runResponse->id;

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
