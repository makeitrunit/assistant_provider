const express = require('express');
const db = require('./database.js');
const app = express();
const port = 5000;

app.use(express.json());

// Ruta para manejar preguntas
app.post('/api/v1/providers', async (req, res) => {
    try {
        let { ubicacion, costo, servicio, categoria } = req.body;

        ubicacion = ubicacion.trim().toLowerCase();
        categoria = categoria.trim().toLowerCase();
        servicio = servicio.trim().toLowerCase();
        costo = parseInt(costo);

        let servicios = {}
        if (servicio) {
            servicios = {
                [db.Sequelize.Op.or]: [
                { informacion: { [db.Sequelize.Op.like]: `%${servicio}%` } },
                { mas_informacion: { [db.Sequelize.Op.like]: `%${servicio}%` } },
                { datos_interes: { [db.Sequelize.Op.like]: `%${servicio}%` } }
            ]
            }
        }

        const providers = await db.providers.findAll({
            where: {
                [db.Sequelize.Op.and]: [
                    // Condición fija para categoría
                    { categoria: { [db.Sequelize.Op.like]: `%${categoria}%` } },

                    // Condición fija para costo (limpiar caracteres no numéricos y comparar como DECIMAL)
                    db.Sequelize.where(
                        db.Sequelize.cast(
                            db.Sequelize.fn('REGEXP_REPLACE', db.Sequelize.col('costo'), '[^0-9.]', ''),
                            'DECIMAL'
                        ),
                        {
                            [db.Sequelize.Op.lte]: costo
                        }
                    ),

                    // Condición fija para ubicación
                    { ubicacion: { [db.Sequelize.Op.like]: `%${ubicacion}%` } },

                    servicios
                ]
            }
        });
        res.json({
            data: providers,
        });

    } catch (error) {
        console.error(error);
        res.status(500).json({error: 'Error al obtener información.'});
    }
});

app.get('/api/v1/categories', async (req, res) => {
    try {
        const categories = await db.categories.findAll({});
        res.json({
            data: categories,
        });

    } catch (error) {
        console.error(error);
        res.status(500).json({error: 'Error al obtener información.'});
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});

