module.exports = (sequelize, Sequelize) => {
    return sequelize.define('categorias_proveedores', {
        categoria: {
            type: Sequelize.DataTypes.STRING,
        }
    }, {
        timestamps: false,
        freezeTableName: true, // Evitar que Sequelize pluralice el nombre de la tabla
        // Configuración para evitar el uso de 'id'
        primaryKey: false, // No hay clave primaria
        hasPrimaryKeys: false, // Indica que no tiene claves primarias
        // Definición de las opciones de la consulta
        defaultScope: {
            attributes: { exclude: ['id'] }, // Asegúrate de excluir la columna id
        }
    });
};