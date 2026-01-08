![img](https://avatars1.githubusercontent.com/u/5365410?s=75) Usuarios y Resultados REST API
======================================

[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Minimum PHP Version](https://img.shields.io/badge/php-%5E8.4-blue.svg)](http://php.net/)
[![PHPUnit Tests](https://github.com/FJavierGil/miw-api-usuarios/actions/workflows/php.yml/badge.svg)](https://github.com/FJavierGil/miw-api-usuarios/actions/workflows/php.yml)
> 🎯 Implementación de una API REST con el framework Symfony para la gestión de usuarios y resultados.

Esta aplicación implementa una interfaz de programación [REST][rest] desarrollada como ejemplo de
utilización del framework [Symfony][symfony]. La aplicación proporciona las operaciones
habituales para la gestión de entidades (usuarios y resultados). Este proyecto
utiliza varios componentes del framework Symfony, [JWT][jwt] (JSON Web Tokens), el _logger_ [Monolog][monolog]
y el [ORM Doctrine][doctrine].

Para hacer más sencilla la gestión de los datos se ha utilizado
el ORM [Doctrine][doctrine]. Doctrine es un Object-Relational Mapper que proporciona
persistencia transparente para objetos PHP. Utiliza el patrón [Data Mapper][dataMapper]
con el objetivo de obtener un desacoplamiento completo entre la lógica de negocio y la
persistencia de los datos en los sistemas de gestión de base de datos.

Por otra parte se incluye parcialmente la especificación de la API (OpenAPI 3.1) . Esta
especificación se ha elaborado empleando el editor [Swagger][swagger]. Adicionalmente se
incluye la interfaz de usuario (SwaggerUI) de esta estupenda herramienta que permite
realizar pruebas interactivas de manera completa y elegante.


## 🚀 Instalación de la aplicación

El primer paso consiste en generar un esquema de base de datos vacío y un usuario/contraseña
con privilegios completos sobre dicho esquema.

A continuación se deberá crear una copia del fichero `./.env` y renombrarla
como `./.env.local`. Después se deberá editar dicho fichero y modificar la variable `DATABASE_URL`
con los siguientes parámetros:

* Nombre y contraseña del usuario generado anteriormente
* Nombre del esquema de bases de datos

Una vez editado el anterior fichero y desde el directorio raíz del proyecto se deben ejecutar los comandos:
```shell

composer update
php bin/console doctrine:schema:update --dump-sql --force
```
El proyecto base entregado incluye el componente [lexik/jwt-authentication-bundle][lexik] para
la generación de los tókens JWT. Siguiendo las instrucciones indicadas en la [documentación][1] de
dicho componente se deberán generar las claves SSH necesarias con los comandos:
```
$ mkdir -p config/secrets/jwt
$ openssl genpkey -out config/secrets/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
$ openssl pkey -in config/secrets/jwt/private.pem -out config/secrets/jwt/public.pem -pubout
```
En la instalación de XAMPP el programa *openssl* se encuentra en el directorio `XAMPP/apache/bin`. El
resto de la configuración ya se ha realizado en este proyecto. Como *pass phrase* se empleará la
especificada en la variable `JWT_PASSPHRASE` en el fichero `.env`.

Para lanzar el servidor con la aplicación en desarrollo, desde la raíz del proyecto
se debe ejecutar el comando: 
```shell

symfony server:start [-d]
```
Antes de probar la interfaz de la API es recomendable crear al menos un usuario con permisos de administrador.
Para conseguir este objetivo se ha proporcionado un comando disponible a través de la consola
de Symfony. La descripción del funcionamiento de este comando puede obtenerse con:
```shell

php bin/console miw:create-user --help
```
A continuación ya se puede realizar una petición con el navegador a la dirección [https://127.0.0.1:8000/][lh]

## 🗄️ Estructura del proyecto:

El contenido y estructura del proyecto es:

* Directorio raíz del proyecto `.`:
    - `.env`: variables de entorno locales por defecto
    - `phpunit.dist.xml` configuración por defecto de la suite de pruebas
    - `README.md`: este fichero
* Directorio `docs`:
    - Documentación autogenerada el framework de pruebas 
* Directorio `bin`:
    - Ejecutables (*console* y *phpunit*)
* Directorio `src`:
    - Contiene el código fuente de la aplicación
    - Subdirectorio `src/Entity`: entidades PHP (incluyen anotaciones de mapeo del ORM)
* Directorio `var`:
    - Ficheros de log y caché (diferenciando entornos).
* Directorio `public`:
    - `index.php` es el controlador frontal de la aplicación. Inicializa y lanza 
      el núcleo de la aplicación.
    - Subdirectorio `api-docs`: cliente [Swagger][swagger] y especificación de la API.
* Directorio `vendor`:
    - Componentes desarrollados por terceros (Symfony, Doctrine, JWT, Monolog, Dotenv, etc.)
* Directorio `tests`:
    - Conjunto de scripts para la ejecución de tests con PHPUnit.

## 🛠️ Ejecución de pruebas

La aplicación incorpora un conjunto de herramientas para la ejecución de pruebas 
unitarias y de integración con [PHPUnit][phpunit]. Empleando este conjunto de herramientas
es posible comprobar de manera automática el correcto funcionamiento de la API completa
sin la necesidad de herramientas adicionales.

Para configurar el entorno de pruebas se debe crear un nuevo esquema de bases de datos vacío,
y una copia del fichero `./phpunit.dist.xml` y renombrarla como `./phpunit.xml`. De igual
forma se deberá crear una copia del fichero `./.env.test` y renombrarla como
`./.env.test.local`. Después se debe editar este último fichero para asignar los
siguientes parámetros:

* Configuración del acceso a la nueva base de datos (variable `DATABASE_URL`)
* E-mail y contraseña de los usuarios (fixtures) que se van a emplear para realizar las pruebas (no
es necesario insertarlos, lo hace automáticamente el método `setUpBeforeClass()`
de la clase `BaseTestCase`)

Para lanzar la suite de pruebas completa se debe ejecutar:
```shell

php ./bin/phpunit [--testdox] [--coverage-text]
```
Adicionalmente, para comprobar la calidad de las pruebas, el proyecto incluye test de mutaciones
generados con la herramienta [Infection][infection].
El funcionamiento es simple: se generan pequeños cambios en el código original (👽 _mutantes_), y a continuación
se ejecuta la batería de pruebas. Si las pruebas fallan, indica que han sido capaces de detectar la modificación
del código, y el mutante es eliminado. Si pasa las pruebas, el mutante sobrevive y la fiabilidad de la prueba
queda cuestionada.

Para lanzar los test de mutaciones se ejecutará:
```shell

composer infection
```

Por último, también se han añadido dos herramientas adicionales para el análisis estático de código, 
[PHPStan][phpstan] y [PhpMetrics][phpmetrics]. PhpStan es una herramienta de análisis estático de código, mientras que
PhpMetrics analiza el código y permite generar informes con diferentes métricas de proyecto.
Estas herramientas pueden ejecutarse a través de los comandos:
```shell

composer phpstan
composer metrics
```

[dataMapper]: http://martinfowler.com/eaaCatalog/dataMapper.html
[doctrine]: http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/
[infection]: https://infection.github.io/guide/
[jwt]: https://jwt.io/
[lh]: https://127.0.0.1:8000/
[monolog]: https://github.com/Seldaek/monolog
[openapi]: https://www.openapis.org/
[phpunit]: http://phpunit.de/manual/current/en/index.html
[rest]: http://www.restapitutorial.com/
[symfony]: https://symfony.com/
[swagger]: http://swagger.io/
[yaml]: https://yaml.org/
[lexik]: https://github.com/lexik/LexikJWTAuthenticationBundle
[1]: https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#generate-the-ssh-keys
[phpmetrics]: https://phpmetrics.org/
[phpstan]: https://phpstan.org/
