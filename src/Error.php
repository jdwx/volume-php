<?php


declare( strict_types = 1 );


namespace JDWX\Volume;


enum Error {


    case PATH_EXISTS;

    case PATH_INVALID;

    case PATH_IS_DIRECTORY;

    case PATH_IS_FILE;

    case PATH_IS_WEIRD;

    case PATH_NOT_FOUND;

    case PATH_PARENT_NOT_DIRECTORY;

    case PATH_PARENT_NOT_FOUND;

    case DIRECTORY_IS_CLOSED;


}
