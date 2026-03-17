<?php

namespace App\Enums;

enum EmployeePosition: string
{
    case VENDEUSE = 'vendeuse';
    case GERANT = 'gerant';
    case MANAGER = 'manager';
    case CAISSIER = 'caissier';
    case STOCKISTE = 'stockiste';

    public function label(): string
    {
        return match($this) {
            self::VENDEUSE => 'Vendeuse',
            self::GERANT => 'Gérant',
            self::MANAGER => 'Manager',
            self::CAISSIER => 'Caissier',
            self::STOCKISTE => 'Stockiste',
        };
    }

    public function baseSalary(): float
    {
        return match($this) {
            self::VENDEUSE => 150000,
            self::GERANT => 300000,
            self::MANAGER => 250000,
            self::CAISSIER => 120000,
            self::STOCKISTE => 130000,
        };
    }
}