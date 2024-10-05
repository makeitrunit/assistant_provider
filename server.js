require('dotenv').config()
const express = require('express');
const OpenAI = require("openai");
const fs = require("fs");
const axios = require("axios");
const cors = require('cors')

const openai = new OpenAI({
    apiKey: process.env.API_KEY,
});


// Setup Express
const app = express();
app.use(cors())
app.use(express.json()); // Middleware to parse JSON bodies

// Assistant can be created via API or UI
const assistantId = process.env.ASSISTANTS_ID
let pollingInterval;

// Set up a Thread
async function createThread() {
    const thread = await openai.beta.threads.create();
    return thread;
}

async function addMessage(threadId, message) {
    // Hacer solicitud a la API externa
    const apiResponse = await axios.get(process.env.API_PROVIDERS_URL);
    const dataApi = apiResponse.data;

    const chat = `
        No olvides que tienes esta fuente externa de datos con la cual estamos trabajando: ${JSON.stringify(dataApi)}.
        Continua la conversación: "${message}".`;
    const response = await openai.beta.threads.messages.create(threadId, {
        role: "user",
        content: chat,
    });
    return response;
}

async function runAssistant(threadId) {
    const response = await openai.beta.threads.runs.create(threadId, {
        assistant_id: assistantId,
    });

    return response;
}

async function checkingStatus(res, threadId, runId) {
    const runObject = await openai.beta.threads.runs.retrieve(threadId, runId);

    const status = runObject.status;

    if (status === "completed") {
        clearInterval(pollingInterval);

        const messagesList = await openai.beta.threads.messages.list(threadId);

        const { data: messages } = messagesList.body;
        const response = messages[0]?.content[0]?.text;

        res.json({ response });
    }
}

// Open a new thread
app.get("/thread", (req, res) => {
    createThread().then((thread) => {
        res.json({ threadId: thread.id });
    });
});

app.post("/message", (req, res) => {
    const { message, threadId } = req.body;
    addMessage(threadId, message).then((message) => {
        // res.json({ messageId: message.id });

        // Run the assistant
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