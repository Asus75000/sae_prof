/**
 * Convertit une chaîne de date au format JJ/MM/AAAA HH:MM ou JJ/MM/AAAA en objet Date JavaScript
 * @param {string} dateStr - Date au format JJ/MM/AAAA HH:MM ou JJ/MM/AAAA
 * @returns {Date|null} Objet Date ou null si invalide
 */
function convertDateTime(dateStr) {
    if(!dateStr) return null;

    // Format : JJ/MM/AAAA HH:MM ou JJ/MM/AAAA
    const parts = dateStr.trim().split(' ');
    const dateParts = parts[0].split('/');

    if(dateParts.length !== 3) return null;

    const day = parseInt(dateParts[0], 10);
    const month = parseInt(dateParts[1], 10) - 1; // Les mois commencent à 0 en JavaScript
    const year = parseInt(dateParts[2], 10);

    let hour = 0, minute = 0;
    if(parts.length > 1) {
        const timeParts = parts[1].split(':');
        hour = parseInt(timeParts[0], 10);
        minute = parseInt(timeParts[1], 10);
    }

    return new Date(year, month, day, hour, minute);
}

/**
 * Initialise le formatage et validation automatique pour un champ de date
 * @param {HTMLInputElement} input - Le champ input de date
 */
function initDateInput(input) {
    // Formatage automatique pendant la saisie
    input.addEventListener('input', function() {
        let value = this.value.replace(/[^\d]/g, '');
        if(value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2);
        }
        if(value.length >= 5) {
            value = value.substring(0, 5) + '/' + value.substring(5, 9);
        }
        this.value = value;
    });
    
    // Validation à la perte de focus
    input.addEventListener('blur', function() {
        const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
        const match = this.value.match(dateRegex);
        if(match) {
            const jour = parseInt(match[1]);
            const mois = parseInt(match[2]);
            const annee = parseInt(match[3]);
            
            if(jour < 1 || jour > 31 || mois < 1 || mois > 12 || annee < 2000) {
                alert('Date invalide. Format: jj/mm/aaaa (ex: 15/10/2025)');
                this.value = '';
            }
        } else if(this.value !== '') {
            alert('Format de date incorrect. Utilisez: jj/mm/aaaa');
            this.value = '';
        }
    });
}

/**
 * Initialise le formatage et validation automatique pour un champ d'heure
 * @param {HTMLInputElement} input - Le champ input d'heure
 */
function initTimeInput(input) {
    // Formatage automatique pendant la saisie
    input.addEventListener('input', function() {
        let value = this.value.replace(/[^\d]/g, '');
        if(value.length >= 2) {
            value = value.substring(0, 2) + ':' + value.substring(2, 4);
        }
        this.value = value;
    });
    
    // Validation à la perte de focus
    input.addEventListener('blur', function() {
        const timeRegex = /^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/;
        const match = this.value.match(timeRegex);
        if(match) {
            const heures = match[1].padStart(2, '0');
            const minutes = match[2];
            this.value = heures + ':' + minutes;
        } else if(this.value !== '') {
            alert('Format d\'heure incorrect. Utilisez le format 24h: HH:MM (ex: 14:30)');
            this.value = '';
        }
    });
}

/**
 * Convertit une date JJ/MM/AAAA en format MySQL AAAA-MM-JJ
 * @param {string} dateStr - Date au format JJ/MM/AAAA
 * @returns {string|null} Date au format MySQL ou null si invalide
 */
function convertDateToMysql(dateStr) {
    const dateRegex = /^(\d{2})\/(\d{2})\/(\d{4})$/;
    const match = dateStr.match(dateRegex);
    if(match) {
        return match[3] + '-' + match[2] + '-' + match[1];
    }
    return null;
}

/**
 * Initialise la conversion automatique de date avant soumission d'un formulaire
 * @param {HTMLFormElement} form - Le formulaire
 * @param {string} dateInputId - L'ID du champ de date à convertir
 */
function initDateConversionOnSubmit(form, dateInputId) {
    const dateInput = document.getElementById(dateInputId);
    if(!dateInput) return;
    
    form.addEventListener('submit', function(e) {
        const mysqlDate = convertDateToMysql(dateInput.value);
        if(mysqlDate) {
            const hiddenDate = document.createElement('input');
            hiddenDate.type = 'hidden';
            hiddenDate.name = dateInputId;
            hiddenDate.value = mysqlDate;
            
            dateInput.disabled = true;
            form.appendChild(hiddenDate);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts après 5 secondes
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => alert.style.display = 'none');
    }, 5000);

    // Validation email
    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('blur', function() {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(this.value && !regex.test(this.value)) {
                this.setCustomValidity('Email invalide');
            } else {
                this.setCustomValidity('');
            }
        });
    });

    // Validation mot de passe
    document.querySelectorAll('input[type="password"]').forEach(input => {
        input.addEventListener('blur', function() {
            if(this.value && this.value.length < 8) {
                this.setCustomValidity('Minimum 8 caractères');
            } else {
                this.setCustomValidity('');
            }
        });
    });

    // GESTION DES ÉVÉNEMENTS - PAGE ADMIN
    const eventForm = document.getElementById('eventForm');
    if(eventForm) {
        const sportFields = document.querySelector('.event-sport-fields');
        const assoFields = document.querySelector('.event-asso-fields');
        const catEventField = document.getElementById('id_cat_event');
        const dateEventAssoField = document.getElementById('date_event_asso');
        const tarifField = document.getElementById('tarif');

        // Afficher les bons champs selon le type (déjà géré par PHP mais on s'assure du required)
        const isSport = sportFields && !sportFields.classList.contains('hidden');

        if(isSport) {
            if(catEventField) catEventField.setAttribute('required', '');
            if(dateEventAssoField) dateEventAssoField.removeAttribute('required');
            if(tarifField) tarifField.removeAttribute('required');
        } else {
            if(dateEventAssoField) dateEventAssoField.setAttribute('required', '');
            if(tarifField) tarifField.setAttribute('required', '');
            if(catEventField) catEventField.removeAttribute('required');
        }

        // Validation des formats de date
        const dateInputs = document.querySelectorAll('input[pattern*="\\\\d{2}/\\\\d{2}/\\\\d{4}"]');
        dateInputs.forEach(input => {
            input.addEventListener('blur', function() {
                const value = this.value.trim();
                if(value && !this.validity.valid) {
                    alert('Format de date incorrect. Utilisez le format JJ/MM/AAAA ou JJ/MM/AAAA HH:MM');
                }
            });
        });

        // Validation : date de clôture doit être avant la date de l'événement (pour les événements associatifs)
        if(!isSport) {
            eventForm.addEventListener('submit', function(e) {
                const dateClotureInput = document.getElementById('date_cloture');
                const dateEventInput = document.getElementById('date_event_asso');

                if(dateClotureInput && dateEventInput) {
                    const dateCloture = convertDateTime(dateClotureInput.value);
                    const dateEvent = convertDateTime(dateEventInput.value);

                    if(dateCloture && dateEvent && dateCloture >= dateEvent) {
                        e.preventDefault();
                        alert('La date de clôture des inscriptions doit être avant la date de l\'événement.');
                        dateClotureInput.focus();
                        return false;
                    }
                }
            });
        }
    }

    // GESTION DES CRÉNEAUX - PAGE ADMIN
    const creneauForm = document.querySelector('form.form-grid[data-form="creneau"]');
    if(creneauForm) {
        const dateInput = document.getElementById('date_creneau');
        const heureDebutInput = document.getElementById('heure_debut');
        const heureFinInput = document.getElementById('heure_fin');
        
        // Utiliser les fonctions de formatage et validation
        if(dateInput) initDateInput(dateInput);
        if(heureDebutInput) initTimeInput(heureDebutInput);
        if(heureFinInput) initTimeInput(heureFinInput);
        
        // Conversion automatique de la date avant soumission
        if(dateInput) {
            initDateConversionOnSubmit(creneauForm, 'date_creneau');
        }
        
        // Validation des heures avant soumission
        creneauForm.addEventListener('submit', function(e) {
            const timeRegex = /^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/;
            if(heureDebutInput && !timeRegex.test(heureDebutInput.value)) {
                e.preventDefault();
                alert('Heure de début invalide. Format: HH:MM (24h)');
                return false;
            }
            if(heureFinInput && !timeRegex.test(heureFinInput.value)) {
                e.preventDefault();
                alert('Heure de fin invalide. Format: HH:MM (24h)');
                return false;
            }
        });
    }
});