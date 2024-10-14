<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$openai = OpenAI::client($_ENV['API_KEY']);

$assistantId = $_ENV['ASSISTANTS_ID'];

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

function consultarProveedores($pdo, $categoria, $costo, $ubicacion, $servicio = "")
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

    $stmt = $pdo->prepare($sql);

    $categoria = "%$categoria%";
    $ubicacion = "%$ubicacion%";
    $servicio = "%$servicio%";

    // Vincular parámetros
    $stmt->bindParam(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindParam(':costo', $costo, PDO::PARAM_STR);
    $stmt->bindParam(':ubicacion', $ubicacion, PDO::PARAM_STR);
    if (!empty($servicio)) {
        $stmt->bindParam(':servicio', $servicio, PDO::PARAM_STR);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    return $openai->beta->threads()->create();
}

function addMessage($openai, $threadId, $message)
{
    return $openai->beta->threads()->messages()->create($threadId, [
        'role' => 'user',
        'content' => $message,
    ]);
}

function runAssistant($openai, $threadId)
{
    global $assistantId;

    if ($assistantId === null) {
        $assistant = $openai->beta->assistants()->create([
            'instructions' => "Te encargaras de ayudarme a organizar bodas, interactuando con el cliente.",
            'model' => 'gpt-4o',
            'tools' => functions(), // Asegúrate de definir la función tools
        ]);
        $assistantId = $assistant->id;
    }

    return $openai->beta->threads()->runs()->create($threadId, [
        'assistant_id' => $assistantId,
    ]);
}

function checkingStatus($openai, $threadId, $runId)
{
    $runObject = $openai->beta->threads()->runs()->retrieve($threadId, $runId);
    $status = $runObject->status;

    if ($status === 'completed') {
        $messagesList = $openai->beta->threads()->messages()->list($threadId);
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
