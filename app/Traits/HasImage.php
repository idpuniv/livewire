<?php
// app/Traits/HasImage.php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

trait HasImage
{
    /**
     * Upload une image et génère les thumbnails
     */
    public function uploadImage($file, $path = 'products', $deleteOld = true)
    {
        if ($deleteOld && $this->image) {
            $this->deleteImage();
        }

        // Générer un nom unique
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();

        // Chemin complet
        $originalPath = "{$path}/originals/{$filename}";
        $thumbnailPath = "{$path}/thumbnails/{$filename}";

        // Sauvegarder l'original
        Storage::disk('public')->put($originalPath, file_get_contents($file));

        // Créer et sauvegarder le thumbnail (200x200)
        $thumbnail = Image::make($file)
            ->fit(200, 200, function ($constraint) {
                $constraint->upsize();
            })
            ->encode($file->getClientOriginalExtension(), 80);

        Storage::disk('public')->put($thumbnailPath, $thumbnail);

        // Sauvegarder le chemin en base (on garde le thumbnail pour l'affichage)
        $this->image = $thumbnailPath;
        $this->save();

        return $this;
    }

    /**
     * Supprime l'image et son thumbnail
     */
    public function deleteImage()
    {
        if ($this->image) {
            // Supprimer le thumbnail
            Storage::disk('public')->delete($this->image);

            // Supprimer l'original (on déduit le chemin)
            $original = str_replace('thumbnails/', 'originals/', $this->image);
            Storage::disk('public')->delete($original);
        }
    }

    /**
     * Récupère l'URL de l'image
     */
    public function getImageUrlAttribute()
    {
        return $this->image
            ? Storage::disk('public')->url($this->image)
            : null;
    }

    /**
     * Récupère l'URL de l'image originale (si besoin)
     */
    public function getOriginalImageUrlAttribute()
    {
        if (!$this->image) {
            return null;
        }

        $original = str_replace('thumbnails/', 'originals/', $this->image);
        return Storage::disk('public')->exists($original)
            ? Storage::disk('public')->url($original)
            : $this->image_url;
    }
}
