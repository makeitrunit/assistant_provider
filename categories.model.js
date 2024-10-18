module.exports = (sequelize, Sequelize) => {
    return sequelize.define('categorias_proveedores', {
        categoria: {
            type: Sequelize.DataTypes.STRING,
        }
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