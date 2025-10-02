<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Folder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'division_id', 'user_id', 'parent_folder_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_folder_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_folder_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function getFullPath(): string
    {
        $this->loadMissing('division', 'parent');
        $pathParts = [];
        $current = $this;
        while ($current) {
            array_unshift($pathParts, Str::slug($current->name, '-'));
            $current = $current->parent; 
        }
        $divisionSlug = $this->division ? Str::slug($this->division->name, '-') : 'divisi-tidak-diketahui';
        return 'uploads/' . $divisionSlug . '/' . implode('/', $pathParts);
    }

    public function getRecursiveSize(): int
    {
        $size = $this->files()->sum('ukuran_file');
        foreach ($this->children()->with('children', 'files')->get() as $child) {
            $size += $child->getRecursiveSize();
        }
        return $size;
    }

    public function getBreadcrumbs()
    {
        $this->loadMissing('parent');
        $breadcrumbs = collect();
        $current = $this;
        while ($current) {
            $breadcrumbs->prepend($current->only(['id', 'name']));
            $current = $current->parent;
        }
        return $breadcrumbs->values();
    }
}