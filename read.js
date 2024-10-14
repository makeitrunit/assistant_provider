const fs = require('fs');
const Papa = require('papaparse');
const db = require('./database');

// Ruta del archivo CSV
const filePath = './proveedores_boda.csv';

// Leer el archivo CSV
fs.readFile(filePath, 'utf8', (err, data) => {
    if (err) {
        console.error('Error leyendo el archivo:', err);
        return;
    }

    // Parsear el contenido del archivo CSV
    Papa.parse(data, {
        header: true, // Si el archivo tiene encabezados, los incluye en el objeto
        skipEmptyLines: true, // Ignora las líneas vacías
        complete: async function (results) {
            for (const fila of results.data) {

                let nombre = fila['Nombre'];
                let url = fila['Url'];
                let categoria = fila['Categoría'];
                let valoracion = fila['Valoración'];
                let opinion = fila['Opinión'];
                let datos_interes = fila['Datos de interés'];
                let ubicacion = fila['Ubicación'];
                let costo = fila['Costo'];
                let informacion = fila['Información'];
                let mas_informacion = fila['Más información'];
                let preguntas_frecuentes = fila['Preguntas frecuentes'];

                let result = await db.providers.create({
                    nombre: nombre,
                    url: url, categoria: categoria, valoracion: valoracion,
                    opinion: opinion, datos_interes: datos_interes,
                    ubicacion: ubicacion, costo: costo,
                    informacion: informacion, mas_informacion: mas_informacion,
                    preguntas_frecuentes: preguntas_frecuentes,
                });

            }
        },
        error: function (error) {
            console.error('Error al parsear el archivo CSV:', error);
        }
    });
});
