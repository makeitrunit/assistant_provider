
module.exports = (sequelize, Sequelize) => {
    return sequelize.define("Fotografos", {
        id: {
            type: Sequelize.DataTypes.INTEGER,
            primaryKey: true,
            autoIncrement: true
        },
        nombre: {
            type: Sequelize.DataTypes.STRING,
        },
        categoria: {
            type: Sequelize.DataTypes.STRING,
        },
        valoracion: {
            type: Sequelize.DataTypes.STRING,
        },
        opinion: {
            type: Sequelize.DataTypes.STRING
        },
        costo: {
            type: Sequelize.DataTypes.STRING
        },
        invitados: {
            type: Sequelize.DataTypes.INTEGER
        },
        datos_interes: {
            type: Sequelize.DataTypes.TEXT
        },
        informacion: {
            type: Sequelize.DataTypes.TEXT
        },
        mas_informacion: {
            type: Sequelize.DataTypes.TEXT
        },
        preguntas_frecuentes: {
            type: Sequelize.DataTypes.TEXT
        },
    }, {
        timestamps: false
    });
};
