<?php declare(strict_types=1);

namespace MyDB\Migration;

enum EType: string {

    case INT = 'INT';
    case FLOAT = 'FLOAT';
    case DOUBLE = 'DOUBLE';
    case STRING = 'STRING';
    case TEXT = 'TEXT';
    case BOOL = 'BOOL';
    case DATETIME = 'DATETIME';
    case DATE = 'DATE';
    case TIME = 'TIME';
    case TIMESTAMP = 'TIMESTAMP';
    case ENUM = 'ENUM';

}