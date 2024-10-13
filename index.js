require('dotenv').config();
const express = require('express');
const axios = require('axios');
const OpenAI = require('openai');

const app = express();
const port = 3000;

// Crear el cliente de OpenAI
const openai = new OpenAI({
    apiKey: process.env.API_KEY,
});

app.use(express.json());

// Ruta para crear un nuevo hilo de chat
app.post('/crear-hilo', async (req, res) => {
    try {

        const assistants = await openai.beta.assistants.list()
        // Crear un nuevo hilo
        const threadResponse = await openai.beta.assistants.create({
            assistants_id: process.env.ASSISTANTS_ID,
        });

        const threadId = threadResponse.id; // Obtén el ID del hilo creado
        res.json({ threadId });
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Error al crear el hilo.' });
    }
});

// Ruta para enviar un mensaje al asistente en un hilo existente
app.post('/enviar-mensaje', async (req, res) => {
    const { threadId, mensaje } = req.body;

    try {

        // Hacer solicitud a la API externa
        const apiResponse = await axios.get(process.env.API_PROVIDERS_URL);
        const dataApi = apiResponse.data;

        // Combina los datos de la API con la pregunta
        const chat = `No olvides que tienes esta fuenta externa de datos con la cual estamos trabajando: ${JSON.stringify(dataApi)}. 
        Continua la conversacion : "${mensaje}".`;

        const responseAI = await openai.beta.assistants.create(threadId, {
            message: {
                role: "user",
                content: chat,
            },
        });

        const respuestaAsistente = responseAI.message.content.trim(); // Respuesta del asistente

        // Envía la respuesta generada por OpenAI al cliente
        res.json({ respuesta: respuestaAsistente });

    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Error al enviar el mensaje.' });
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});