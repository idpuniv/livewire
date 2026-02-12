<?php

namespace App\Enums;

enum Status: string
{
    case DRAFT      = 'draft';        // Brouillon
    case PENDING    = 'pending';      // En attente
    case ACTIVE     = 'active';       // Actif
    case INACTIVE   = 'inactive';     // Inactif
    case CONFIRMED  = 'confirmed';    // Confirmé
    case COMPLETED  = 'completed';    // Terminé
    case CANCELLED  = 'cancelled';    // Annulé
    case FAILED     = 'failed';       // Échec
    case SUCCESS    = 'success';      // Succès
    case PAID       = 'paid';         // Payé
    case UNPAID     = 'unpaid';       // Non payé
    case PARTIAL    = 'partial';      // Partiel
    case REFUNDED   = 'refunded';     // Remboursé
    case ARCHIVED   = 'archived';     // Archivé
}
