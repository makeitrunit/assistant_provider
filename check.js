require('dotenv').config()
const OpenAI = require('openai');

const openai = new OpenAI({
    apiKey: process.env.API_KEY,
});

async function listarModelos() {
    try {
        const modelos = await openai.models.list();
        console.log(modelos);
    } catch (error) {
        console.error(error);
    }
}

listarModelos();