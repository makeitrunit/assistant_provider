module.exports = (sequelize, Sequelize) => {
  return sequelize.define('proveedores', {
        id: {
            type: Sequelize.DataTypes.INTEGER,
            allowNull: false,
            primaryKey: true,
            autoIncrement: true
        },
        nombre: {
            type: Sequelize.DataTypes.STRING(255),
            allowNull: true,
        },
        categoria: {
            type: Sequelize.DataTypes.STRING(100),
            allowNull: true,
        },
        valoracion: {
            type: Sequelize.DataTypes.STRING(50),
            allowNull: true,
        },
        opinion: {
            type: Sequelize.DataTypes.STRING(255),
            allowNull: true,
        },
        ubicacion: {
            type: Sequelize.DataTypes.STRING(255),
            allowNull: true,
        },
        costo: {
            type: Sequelize.DataTypes.STRING(100),
            allowNull: true,
        },
        invitados: {
            type: Sequelize.DataTypes.INTEGER,
            allowNull: true,
        },
        datos_interes: {
            type: Sequelize.DataTypes.TEXT,
            allowNull: true,
        },
        informacion: {
            type: Sequelize.DataTypes.TEXT,
            allowNull: true,
        },
        mas_informacion: {
            type: Sequelize.DataTypes.TEXT,
            allowNull: true,
        },
        preguntas_frecuentes: {
            type: Sequelize.DataTypes.TEXT,
            allowNull: true,
        }
    }, {
        timestamps: false, // No incluye campos `createdAt` y `updatedAt`
    });
};
