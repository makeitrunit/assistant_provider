<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; // Cargar las dependencias de Composer

const DB_HOST = "";
const DB_NAME = "";
const DB_USER = "";
const DB_PASS = "";
const ASSISTANTS_ID = "";
const API_KEY = ""; // Debes definir la clave aquí o usar $_ENV si lo prefieres

// Inicializar cliente OpenAI

$openai = OpenAI::client(API_KEY);

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

// Consulta a la base de datos
function fetchDataFromDatabase($pdo) {
    $sql = "SELECT * FROM Fotografos"; // Ajusta el nombre de la tabla y columnas
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Crear un nuevo thread
function createThread($openai) {
    return $openai->threads->create(); // Ajustado
}

// Agregar un mensaje al thread
function addMessage($openai, $pdo, $threadId, $message) {
    // Obtener datos de la base de datos
    $dataFromDb = fetchDataFromDatabase($pdo);

    // Formar el mensaje
    $chat = "No olvides que tienes esta fuente de datos (desde la base de datos): "
        . json_encode($dataFromDb) .
        ". Continua la conversación: \"$message\".";

    // Enviar el mensaje al thread
    return $openai->threads->messages->create($threadId, [
        'role' => 'user',
        'content' => $chat,
    ]);
}

// Ejecutar el asistente
function runAssistant($openai, $threadId, $assistantId) {
    return $openai->threads->runs->create($threadId, [
        'assistant_id' => $assistantId,
    ]);
}

// Revisar el estado del run
function checkingStatus($openai, $threadId, $runId) {
    $runObject = $openai->threads->runs->retrieve($threadId, $runId);
    $status = $runObject->status;

    if ($status === 'completed') {
        $messagesList = $openai->threads->messages->list($threadId);
        $messages = $messagesList->data; // Ajustado según la estructura de datos

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

        // Chequear el estado periódicamente
        sleep(5); // Espera de 5 segundos
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