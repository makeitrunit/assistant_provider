module.exports = (sequelize, Sequelize) => {
    return sequelize.define('proveedores_imagenes', {
        id: {
            type: Sequelize.DataTypes.INTEGER,
            allowNull: false,
            primaryKey: true,
            autoIncrement: true
        },
        proveedores_id: {
            type: Sequelize.DataTypes.INTEGER,
            allowNull: false,
        },
        url: {
            type: Sequelize.DataTypes.TEXT,
            allowNull: true,
        },
    }, {
        timestamps: false,
        freezeTableName: true,
        primaryKey: false,
        hasPrimaryKeys: false,
        defaultScope: {
            attributes: { exclude: ['id'] },
        }
    });
};