<?php


declare( strict_types = 1 );


require_once __DIR__ . '/../vendor/autoload.php';


(static function() : void {

    $vol = new JDWX\Volume\Volume();
    $vol->writeFile( '/Dockerfile', <<<ZEND
        FROM node:lts-alpine
        WORKDIR /sandbox
        COPY package.json ./
        RUN npm install --production
        ZEND
    );
    $vol->writeFile( '/package.json', <<<ZEND
        {
          "name": "example",
          "private": "true",
          "type": "module",
          "dependencies": {
            "typescript": "^5.7.0"
          }
        }        
        ZEND
    );
    passthru( 'ls -laR ' . $vol->path() );


})();
