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

function fetchDataFromDatabase($pdo)
{
    $sql = "SELECT * FROM Fotografos";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createThread($openai)
{
    return $openai->threads()->create([]);
}

function addMessage($openai, $pdo, $threadId, $message)
{
    $dataFromDb = fetchDataFromDatabase($pdo);
    array_walk_recursive($dataFromDb, function (&$value) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    });
    $jsonData = json_encode(['data' => $dataFromDb]);


    if ($jsonData === false) {
        echo 'Error en la codificación JSON: ' . json_last_error_msg();
    }

    $chat = "Aqui tienes los datos los cuales usaremos de ahora en adelante: " . $jsonData . ". Responde esta peticion: ".$message;

    return $openai->threads()->messages()->create($threadId, [
        'role' => 'user',
        'content' => $chat,
    ]);
}

function runAssistant($openai, $threadId, $assistantId)
{

    return $openai->threads()->runs()->create($threadId, [
        'assistant_id' => $assistantId,
    ]);
}

function checkingStatus($openai, $threadId, $runId)
{
    $runObject = $openai->threads()->runs()->retrieve($threadId, $runId);
    $status = $runObject->status;

    if ($status === 'completed') {
        $messagesList = $openai->threads()->messages()->list($threadId);
        $messages = $messagesList->data; // Ajustado según la estructura de datos


        if (!empty($messages)) {
            return $messages[0]->content[0]['text'];
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

        $messageResponse = addMessage($openai, $pdo, $threadId, $message);
        $runResponse = runAssistant($openai, $threadId, $assistantId);
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

