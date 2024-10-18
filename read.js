const fs = require('fs');
const Papa = require('papaparse');
const db = require('./database');

const filePath = './proveedores_imagenes.csv';

//const filePath2 = './proveedores_imagenes.csv';

async function findProvider(nombre) {
    return await db.providers.findOne({
        attributes: ['id', 'nombre'],
        where: {
            nombre: {[db.Sequelize.Op.like]: `%${nombre}%`}
        },
        limit: 1,
    });
}

let datos = ""
let batch = []

fs.readFile(filePath, 'utf8', (err, data) => {
    if (err) {
        console.error('Error leyendo el archivo:', err);
        return;
    }

    Papa.parse(data, {
        header: false,
        skipEmptyLines: true,
        complete: async function (results) {
            for (const fila of results.data) {
                console.log(fila[0])
                let provider = await findProvider(fila[0]);

                if (provider) {
                    batch.push({
                        proveedores_id: provider.id,
                        url: fila[1],
                    })
                }

                if (batch.length === 1000) {
                    let result = await db.providers_images.bulkCreate(batch);
                    batch = []
                }

            }
            fs.writeFileSync(filePath2, datos);
        },
        error: function (error) {
            console.error('Error al parsear el archivo CSV:', error);
        }
    });
});
