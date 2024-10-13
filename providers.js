const express = require('express');
const db = require('./database.js');
const app = express();
const port = 5000;

app.use(express.json());

// Ruta para manejar preguntas
app.get('/api/v1/providers', async (req, res) => {
    try {
        let {lugar, costo, servicios, categoria} = req.body

        lugar = lugar.trim()
        categoria = categoria.trim()
        costo = parseInt(costo.trim())

        const providers = await db.providers.findAll({
            where: {
                [db.Sequelize.Op.and]: [
                    {
                        'categoria': categoria
                    }
                ],
                [db.Sequelize.Op.and]: db.Sequelize.where(
                    db.Sequelize.cast(
                        db.Sequelize.fn('REGEXP_REPLACE', db.Sequelize.col('costo'), '[^0-9.]', ''),
                        'DECIMAL'
                    ),
                    {
                        [db.Sequelize.Op.lt]: costo
                    }
                ),
                [db.Sequelize.Op.and]: [
                    { mas_informacion: { [db.Sequelize.Op.like]: `%${servicios}%` } },
                ],
                [db.Sequelize.Op.and]: [
                    { mas_informacion: { [db.Sequelize.Op.like]: `%${servicios}%` } },
                ]
            },
        });
        res.json({
            data: providers,
        });

    } catch (error) {
        console.error(error);
        res.status(500).json({error: 'Error al obtener información.'});
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});

