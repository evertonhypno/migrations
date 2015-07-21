migration config file example

path: migrations
version_table: db_version
databases: 
    -
        dbname: hypno
        user: root
        password: ~
        host: localhost
        driver: pdo_mysql