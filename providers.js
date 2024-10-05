require('dotenv').config()
const express = require('express');
const db = require('./database.js');
const app = express();
const port = 5000;

app.use(express.json());

// Ruta para manejar preguntas
app.get('/api/v1/providers', async (req, res) => {
    try {
        const providers = await db.providers.findAll({});
        res.json({
            data: providers,
        });

    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Error al obtener información.' });
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});

