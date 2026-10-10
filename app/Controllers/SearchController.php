<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Core\Controller;
use App\Services\SearchService;

final class SearchController extends Controller
{
    public function index(): void
    {
        $term = $this->str('q');

        $this->view('search/index', [
            'title' => 'البحث الشامل',
            'term' => $term,
            'results' => $term !== '' ? SearchService::search($term) : [],
        ]);
    }
}
