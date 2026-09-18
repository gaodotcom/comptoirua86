/**
 * Gestion des champs de date facultatifs avec bouton pour clear
 */
document.addEventListener('DOMContentLoaded', function() {
    const endDateInput = document.getElementById('end_date');
    const clearBtn = document.getElementById('end_date_clear_btn');

    if (!endDateInput || !clearBtn) {
        return; // Les éléments ne sont pas sur cette page
    }

    // Fonction pour mettre à jour l'état du bouton
    function updateButtonState() {
        if (endDateInput.value === '') {
            clearBtn.classList.add('disabled');
            clearBtn.setAttribute('disabled', 'disabled');
        } else {
            clearBtn.classList.remove('disabled');
            clearBtn.removeAttribute('disabled');
        }
    }

    // Initialiser l'état du bouton au chargement
    updateButtonState();

    // Ajouter le listener sur le bouton clear
    clearBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (endDateInput.value !== '') {
            endDateInput.value = '';
            endDateInput.focus();
            updateButtonState();
        }
    });

    // Mettre à jour l'état du bouton quand l'utilisateur tape
    endDateInput.addEventListener('change', updateButtonState);
    endDateInput.addEventListener('input', updateButtonState);
});
