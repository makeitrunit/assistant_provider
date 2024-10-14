require('dotenv').config();
const express = require('express');
const OpenAI = require("openai");
const fs = require("fs");
const axios = require("axios");
const cors = require('cors');
const {response} = require("express");

const openai = new OpenAI({
    apiKey: process.env.API_KEY,
});

// Setup Express
const app = express();
app.use(cors());
app.use(express.json()); // Middleware to parse JSON bodies

// Assistant can be created via API or UI
//const assistantId = process.env.ASSISTANTS_ID;
let assistantId;
let pollingInterval;

async function consultarProveedores(categoria, costo, ubicacion, servicio = "") {
    try {
        const apiResponse = await axios.post(process.env.API_PROVIDERS_URL, {
            categoria: categoria, costo: costo, ubicacion: ubicacion, servicio: servicio,
        });
        return apiResponse.data;
    } catch (error) {
        console.error('Error al consultar proveedores:', error);
        throw new Error('Hubo un error al consultar los proveedores');
    }
}


async function consultarCategorias() {
    try {
        const apiResponse = await axios.get(process.env.API_CATEGORIES_URL, {
            params: {}
        });
        return apiResponse.data;
    } catch (error) {
        console.error('Error al consultar proveedores:', error);
        throw new Error('Hubo un error al consultar los proveedores');
    }
}

// Paso 1: Definir las funciones disponibles para el asistente
const functions = [
    {
        type: "function",
        function: {
            name: "consultar_proveedores",
            description: "Consulta proveedores filtrando por categoría, costo, ubicación, y servicio.",
            parameters: {
                type: "object",
                properties: {
                    categoria: {
                        type: "string",
                        description: "La categoría de los proveedores, por ejemplo: eventos, música, catering, dj, Viaje de novios,Belleza Novias,Vídeo .",
                    },
                    costo: {
                        type: "number",
                        description: "El costo máximo que el cliente está dispuesto a pagar.",
                    },
                    ubicacion: {
                        type: "string",
                        description: "La ubicación donde se requiere el servicio.Son ciudades de España, por ejemplo: Barbastro, Huesca. Ourense, Orense.A Coruña.Cieza, Murcia",
                    },
                    servicio: {
                        type: "string",
                        description: "Palabra clave del servicio que se busca, por ejemplo: DJ, fotógrafo.",
                    },
                },
                required: ["categoria", "costo", "ubicacion"],
            },
        }
    },
    {
        type: "function",
        function: {
            name: "listar_categorias_proveedores",
            description: "Lista las categorías de proveedores disponibles.",
            parameters: {
                type: "object",
                properties: {},
                required: []
            }
        }
    }
];

// Set up a Thread
async function createThread() {
    const thread = await openai.beta.threads.create();
    return thread;
}

async function addMessage(threadId, message) {
    // El mensaje del usuario se envía al asistente
    return openai.beta.threads.messages.create(threadId, {
        role: "user",
        content: message,
    });
}

async function runAssistant(threadId) {
    if (assistantId === undefined) {
        let assistant = await openai.beta.assistants.create({
            "instructions": "Te encargaras de ayudarme a organizar bodas, interactuando con el cliente, donde se te preguntara por catgeorias, servicios y costos de distintos proveedores que podras consultar",
            "model": "gpt-4o",
            "tools": functions,

        });
        assistantId = assistant.id
    }


    const response = await openai.beta.threads.runs.create(threadId, {
        "assistant_id": assistantId,

    });

    return response;
}

async function checkingStatus(res, threadId, runId) {
    const runObject = await openai.beta.threads.runs.retrieve(threadId, runId);

    const status = runObject.status;
    console.log(status)
    if (status === "completed") {
        clearInterval(pollingInterval);

        const messagesList = await openai.beta.threads.messages.list(threadId);

        const {data: messages} = messagesList.body;
        const response = messages[0]?.content[0]?.text;

        if (!res.headersSent) {
            res.json({response});
        }
    } else if (status === 'requires_action') {

        if (runObject.required_action.type === 'submit_tool_outputs') {
            const tool_calls = await runObject.required_action.submit_tool_outputs.tool_calls


            for (const tool_call of tool_calls) {
                if (tool_call.function.name === "listar_categorias_proveedores") {
                    const categoriasData = await consultarCategorias();
                    const run = await openai.beta.threads.runs.submitToolOutputs(
                        threadId,
                        runId,
                        {
                            tool_outputs: [
                                {
                                    tool_call_id: tool_call.id,
                                    output: JSON.stringify(categoriasData)
                                },
                            ],
                        }
                    )
                    console.log('Run after submit tool outputs: ' + run.status)
                }
                if (tool_call.function.name === "consultar_proveedores") {
                    const {categoria, costo, ubicacion, servicio} = JSON.parse(tool_call.function.arguments);

                    const categoriasData = await consultarProveedores(categoria, costo, ubicacion, servicio);
                    const run = await openai.beta.threads.runs.submitToolOutputs(
                        threadId,
                        runId,
                        {
                            tool_outputs: [
                                {
                                    tool_call_id: tool_call.id,
                                    output: JSON.stringify(categoriasData)
                                },
                            ],
                        }
                    )
                    console.log('Run after submit tool outputs: ' + run.status)
                }
            }
        }
    } else if (status === 'in_progress') {
        console.log('El proceso está en progreso, volviendo a comprobar el estado...');
        setTimeout(async () => {
            await checkingStatus(res, threadId, runId);
        }, 3000);
    } else if (status === 'failed') {
        if (!res.headersSent) {
            res.json({response: 'Puedes volver a realizar la pregunta por favor'});
        }
    } else {
        if (!res.headersSent) {
            res.json({response: 'Estado no manejado'});
        }
    }
}

// Open a new thread
app.get("/thread", (req, res) => {
    createThread().then((thread) => {
        res.json({threadId: thread.id});
    });
});

// POST para recibir el mensaje del usuario
app.post("/message", (req, res) => {
    const {message, threadId} = req.body;
    addMessage(threadId, message).then(() => {

        runAssistant(threadId).then((run) => {
            const runId = run.id;

            // Check the status
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
