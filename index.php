<?php

require 'vendor/autoload.php'; // Cargar las dependencias de Composer
use OpenAI\Client as OpenAIClient;
use PDO;

const DB_HOST="";
const DB_NAME="";
const DB_USER="";
const DB_PASS="";
const ASSISTANTS_ID = "";
const API_KEY = "";

// Inicializar cliente OpenAI
$openai = OpenAIClient::factory([
    'apiKey' => $_ENV['API_KEY']
]);

$assistantId = ASSISTANTS_ID;

// Función para conectarse a la base de datos MySQL
function connectToDatabase() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo "Error de conexión a la base de datos: " . $e->getMessage();
        exit;
    }
}

// Consulta a la base de datos en lugar de llamada a la API externa
function fetchDataFromDatabase($pdo) {
    $sql = "SELECT * FROM Fotografos"; // Asegúrate de ajustar el nombre de la tabla y columnas
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $data;
}

// Crear un nuevo thread
function createThread($openai) {
    $thread = $openai->beta->threads->create();
    return $thread;
}

// Agregar un mensaje al thread
function addMessage($openai, $pdo, $threadId, $message) {
    // Obtener datos de la base de datos
    $dataFromDb = fetchDataFromDatabase($pdo);

    // Formar el mensaje con la fuente de datos de la base de datos
    $chat = "No olvides que tienes esta fuente de datos (desde la base de datos): "
            . json_encode($dataFromDb) .
            ". Continua la conversación: \"$message\".";

    // Enviar el mensaje al thread
    $response = $openai->beta->threads->messages->create($threadId, [
        'role' => 'user',
        'content' => $chat,
    ]);

    return $response;
}

// Ejecutar el asistente
function runAssistant($openai, $threadId, $assistantId) {
    $response = $openai->beta->threads->runs->create($threadId, [
        'assistant_id' => $assistantId,
    ]);

    return $response;
}

// Revisar el estado del run y obtener el resultado
function checkingStatus($openai, $threadId, $runId) {
    $runObject = $openai->beta->threads->runs->retrieve($threadId, $runId);
    $status = $runObject->status;

    if ($status === 'completed') {
        $messagesList = $openai->beta->threads->messages->list($threadId);
        $messages = $messagesList->body->data;

        return end($messages)->content; // Retorna el último mensaje
    }

    return null;
}

// Procesar las solicitudes del servidor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conectar a la base de datos
    $pdo = connectToDatabase();

    // Ruta para manejar /message
    if ($_POST['action'] === 'message') {
        $message = $_POST['message'];
        $threadId = $_POST['threadId'];

        $messageResponse = addMessage($openai, $pdo, $threadId, $message);
        $runResponse = runAssistant($openai, $threadId, $assistantId);
        $runId = $runResponse->id;

        // Chequear el estado periódicamente (aquí simplificado para la demo)
        sleep(5); // Espera de 5 segundos para simular el polling
        $response = checkingStatus($openai, $threadId, $runId);

        echo json_encode(['response' => $response]);
    }

    // Ruta para manejar /thread
    elseif ($_GET['action'] === 'thread') {
        $thread = createThread($openai);
        echo json_encode(['threadId' => $thread->id]);
    }
}

?>
