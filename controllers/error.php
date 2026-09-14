<?php

declare(strict_types=1);

/**
 * Page d'erreur 404 — affichée quand aucune route ne correspond.
 */

http_response_code(404);
twig_render('pages/error.twig', ['title' => 'Page introuvable']);
