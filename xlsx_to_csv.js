const XLSX = require('xlsx');
const fs = require('fs');

// Cargar el archivo XLSX
const workbook = XLSX.readFile('./ProveedoresBoda.xlsx');

// Seleccionar la primera hoja (puedes cambiar esto si es necesario)
const sheetName = workbook.SheetNames[0]; // Primera hoja
const worksheet = workbook.Sheets[sheetName];

// Convertir la hoja a CSV
const csvData = XLSX.utils.sheet_to_csv(worksheet);

// Guardar el archivo CSV
fs.writeFileSync('./proveedores_boda.csv', csvData);

console.log('Archivo CSV generado con éxito.');