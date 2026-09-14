<?php

declare(strict_types=1);

/**
 * Formulaire d'ajout / modification d'un événement (administration).
 * En création, l'événement est rattaché à l'administrateur connecté.
 */

// ?id>0 indique une édition, sinon c'est une création.
$eventId = (int) ($_GET['id'] ?? 0);
$isEditing = $eventId > 0;
$editingEvent = $isEditing ? get_event_by_id($eventId) : null;

if ($isEditing && $editingEvent === null) {
    set_flash('danger', 'Introuvable.');
    redirect_to('events');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('event-form', $isEditing ? ['id' => $eventId] : []);
    }

    try {
        if ($isEditing) {
            update_event($eventId, $_POST);
            set_flash('success', 'Modifié.');
        } else {
            $p = $_POST;
            $p['created_by'] = (int) (current_user()['id'] ?? 0);
            create_event($p);
            set_flash('success', 'Ajouté.');
        }
        redirect_to('events');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect_to('event-form', $isEditing ? ['id' => $eventId] : []);
    }
}

twig_render('pages/events/event-form.twig', [
    'title' => $isEditing ? 'Modifier l\'événement' : 'Ajouter un événement',
    'isEditing' => $isEditing,
    'eventId' => $eventId,
    'eventTitle' => $isEditing ? (string) $editingEvent['title'] : '',
    'startDate' => $isEditing ? (string) $editingEvent['start_date'] : '',
    'endDate' => $isEditing ? (string) ($editingEvent['end_date'] ?? '') : '',
    'location' => $isEditing ? (string) ($editingEvent['location'] ?? '') : '',
    'description' => $isEditing ? (string) ($editingEvent['description'] ?? '') : '',
    'published' => $isEditing ? (int) ($editingEvent['published'] ?? 1) : 1,
]);
