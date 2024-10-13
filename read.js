const fs = require('fs');
const Papa = require('papaparse');

// Ruta del archivo CSV
const filePath = './proveedores_weeding.csv';

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
        complete: function (results) {
            // results.data es un array con las filas del CSV
            results.data.forEach((fila, index) => {
                console.log(`Fila ${index + 1}:`, fila);

                if (index == 2) {
                    return;
                }
            });
        },
        error: function (error) {
            console.error('Error al parsear el archivo CSV:', error);
        }
    });
});
