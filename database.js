const Sequelize = require('@sequelize/core');

const sequelize = new Sequelize({
    dialect: 'mariadb',
    database: process.env.DATABASE,
    user: process.env.USER,
    password: process.env.PASSWORD,
    host: process.env.HOST,
    port: 3306,
    showWarnings: true,
    connectTimeout: 1000,
});

const db = {
    Sequelize: Sequelize,
    sequelize: sequelize,
};

db.providers = require("./providers.model.js")(sequelize, Sequelize);

module.exports = db;
