<?php declare(strict_types=1);

namespace MyDB\Migration;

enum EType: string {

    case INT = 'INT';
    case TINYINT = 'TINYINT';
    case SMALLINT = 'SMALLINT';
    case BIGINT = 'BIGINT';
    case BOOLEAN = 'BOOLEAN';
    case JSON = 'JSON';
    case FLOAT = 'FLOAT';
    case POINT = 'POINT';
    case DOUBLE = 'DOUBLE';
    case DECIMAL = 'DECIMAL';
    case STRING = 'STRING';
    case TEXT = 'TEXT';
    case BOOL = 'BOOL';
    case DATETIME = 'DATETIME';
    case DATE = 'DATE';
    case TIME = 'TIME';
    case TIMESTAMP = 'TIMESTAMP';
    case ENUM = 'ENUM';

}