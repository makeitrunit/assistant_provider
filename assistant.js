require('dotenv').config();
const express = require('express');
const OpenAI = require("openai");
const axios = require("axios");
const cors = require('cors');

const openai = new OpenAI({
    apiKey: process.env.API_KEY,
});

// Setup Express
const app = express();
app.use(cors());
app.use(express.json()); // Middleware to parse JSON bodies

// Assistant can be created via API or UI
const assistantId = process.env.ASSISTANTS_ID;
let pollingInterval;

// Función para filtrar proveedores (se invocará desde el assistant)
async function filtrarProveedores(lugar, presupuesto, servicios) {
    try {
        // Llamada a la API externa para obtener la lista de proveedores
        const apiResponse = await axios.get(process.env.API_PROVIDERS_URL);
        const proveedores = apiResponse.data;

        // Filtrar proveedores según los parámetros
        const proveedoresFiltrados = proveedores.filter(proveedor => {
            const coincideLugar = lugar ? proveedor.lugar === lugar : true;
            const coincidePresupuesto = presupuesto ? proveedor.presupuesto <= presupuesto : true;
            const coincideServicios = servicios.length
                ? servicios.every(servicio => proveedor.servicios.includes(servicio))
                : true;

            return coincideLugar && coincidePresupuesto && coincideServicios;
        });

        return proveedoresFiltrados;
    } catch (error) {
        console.error("Error al filtrar proveedores:", error);
        return [];
    }
}

// Set up a Thread
async function createThread() {
    const thread = await openai.beta.threads.create();
    return thread;
}

// Agregar mensaje y generar respuesta
async function addMessage(threadId, message) {
    // Crear el chat inicial, donde el usuario menciona el mensaje y el assistant decide si debe llamar la función
    const response = await openai.beta.threads.messages.create(threadId, {
        role: "user",
        content: message,
    });
    return response;
}

// Ejecutar el assistant y verificar si se llama a la función
async function runAssistant(threadId) {
    const response = await openai.beta.threads.runs.create(threadId, {
        assistant_id: assistantId,
    });

    return response;
}

// Verificar el estado de la ejecución y manejar respuestas
async function checkingStatus(res, threadId, runId) {
    const runObject = await openai.beta.threads.runs.retrieve(threadId, runId);
    const status = runObject.status;

    if (status === "completed") {
        clearInterval(pollingInterval);

        const messagesList = await openai.beta.threads.messages.list(threadId);
        const messages = messagesList.data;

        // Verificar si se realizó una llamada a una función
        const lastMessage = messages[messages.length - 1];
        if (lastMessage.function_call) {
            const functionCall = lastMessage.function_call;
            const args = JSON.parse(functionCall.arguments);

            // Llamar a la función `filtrarProveedores` si fue solicitada
            if (functionCall.name === "filtrarProveedores") {
                const proveedoresFiltrados = await filtrarProveedores(args.lugar, args.presupuesto, args.servicios);

                // Responder con la lista filtrada de proveedores
                res.json({ response: proveedoresFiltrados });
                return;
            }
        }

        // Respuesta normal del assistant (si no se llamó ninguna función)
        const responseMessage = lastMessage.content;
        res.json({ response: responseMessage });
    }
}

// Endpoint para abrir un nuevo hilo
app.get("/thread", (req, res) => {
    createThread().then((thread) => {
        res.json({ threadId: thread.id });
    });
});

// Endpoint para agregar un mensaje y ejecutar el assistant
app.post("/message", (req, res) => {
    const { message, threadId } = req.body;
    addMessage(threadId, message).then(() => {
        // Ejecutar el assistant
        runAssistant(threadId).then((run) => {
            const runId = run.id;

            // Verificar el estado cada 5 segundos
            pollingInterval = setInterval(() => {
                checkingStatus(res, threadId, runId);
            }, 5000);
        });
    });
});

// Start the server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server is running on port ${PORT}`);
});
