/**
 * RMCU Admin JavaScript
 * 
 * @package RMCU_Plugin
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Objet principal du plugin
    window.RMCU_Admin = {
        
        // Initialisation
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initDataTables();
            this.initCharts();
            this.initNotifications();
            this.initAjaxForms();
            this.initMediaUploader();
            this.initColorPicker();
            this.initDatePicker();
        },

        // Liaison des événements
        bindEvents: function() {
            // Bouton de test de connexion API
            $('#rmcu-test-api').on('click', this.testApiConnection);
            
            // Export des données
            $('#rmcu-export-data').on('click', this.exportData);
            
            // Import des données
            $('#rmcu-import-data').on('change', this.importData);
            
            // Synchronisation manuelle
            $('#rmcu-sync-now').on('click', this.syncNow);
            
            // Nettoyage du cache
            $('#rmcu-clear-cache').on('click', this.clearCache);
            
            // Activation/Désactivation des modules
            $('.rmcu-module-toggle').on('change', this.toggleModule);
            
            // Sauvegarde des paramètres via Ajax
            $('#rmcu-settings-form').on('submit', this.saveSettings);
            
            // Recherche en temps réel
            $('#rmcu-search').on('keyup', this.liveSearch);
            
            // Actions en masse
            $('#rmcu-bulk-action').on('click', this.bulkAction);
            
            // Réinitialisation des paramètres
            $('#rmcu-reset-settings').on('click', this.resetSettings);
        },

        // Initialisation des onglets
        initTabs: function() {
            $('.rmcu-tabs').each(function() {
                var $tabs = $(this);
                var $navItems = $tabs.find('.nav-tab');
                var $panels = $tabs.find('.tab-panel');
                
                $navItems.on('click', function(e) {
                    e.preventDefault();
                    var target = $(this).data('tab');
                    
                    $navItems.removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    
                    $panels.removeClass('active');
                    $('#' + target).addClass('active');
                    
                    // Sauvegarder l'onglet actif
                    localStorage.setItem('rmcu_active_tab', target);
                });
                
                // Restaurer l'onglet actif
                var activeTab = localStorage.getItem('rmcu_active_tab');
                if (activeTab) {
                    $navItems.filter('[data-tab="' + activeTab + '"]').click();
                }
            });
        },

        // Initialisation des DataTables
        initDataTables: function() {
            if ($.fn.DataTable) {
                $('.rmcu-datatable').DataTable({
                    language: {
                        url: rmcu_admin.datatables_lang
                    },
                    pageLength: 25,
                    order: [[0, 'desc']],
                    responsive: true,
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf', 'print'
                    ]
                });
            }
        },

        // Initialisation des graphiques
        initCharts: function() {
            if (typeof Chart !== 'undefined') {
                // Graphique des statistiques
                var statsCanvas = document.getElementById('rmcu-stats-chart');
                if (statsCanvas) {
                    var ctx = statsCanvas.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: rmcu_admin.chart_labels,
                            datasets: [{
                                label: 'Vues',
                                data: rmcu_admin.chart_views,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            }, {
                                label: 'Conversions',
                                data: rmcu_admin.chart_conversions,
                                borderColor: 'rgb(255, 99, 132)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Statistiques du mois'
                                }
                            }
                        }
                    });
                }

                // Graphique en camembert
                var pieCanvas = document.getElementById('rmcu-pie-chart');
                if (pieCanvas) {
                    var pieCtx = pieCanvas.getContext('2d');
                    new Chart(pieCtx, {
                        type: 'pie',
                        data: {
                            labels: rmcu_admin.pie_labels,
                            datasets: [{
                                data: rmcu_admin.pie_data,
                                backgroundColor: [
                                    'rgb(255, 99, 132)',
                                    'rgb(54, 162, 235)',
                                    'rgb(255, 205, 86)',
                                    'rgb(75, 192, 192)'
                                ]
                            }]
                        }
                    });
                }
            }
        },

        // Système de notifications
        initNotifications: function() {
            // Fermeture des notifications
            $('.rmcu-notice .notice-dismiss').on('click', function() {
                var noticeId = $(this).closest('.rmcu-notice').data('notice-id');
                if (noticeId) {
                    $.post(rmcu_admin.ajax_url, {
                        action: 'rmcu_dismiss_notice',
                        notice_id: noticeId,
                        nonce: rmcu_admin.nonce
                    });
                }
            });

            // Notifications toast
            this.showToast = function(message, type) {
                var toast = $('<div class="rmcu-toast ' + type + '">' + message + '</div>');
                $('body').append(toast);
                
                setTimeout(function() {
                    toast.addClass('show');
                }, 100);
                
                setTimeout(function() {
                    toast.removeClass('show');
                    setTimeout(function() {
                        toast.remove();
                    }, 300);
                }, 3000);
            };
        },

        // Formulaires Ajax
        initAjaxForms: function() {
            $('.rmcu-ajax-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submit = $form.find('[type="submit"]');
                var originalText = $submit.text();
                
                $submit.prop('disabled', true).text('Traitement...');
                
                $.ajax({
                    url: rmcu_admin.ajax_url,
                    type: 'POST',
                    data: $form.serialize() + '&nonce=' + rmcu_admin.nonce,
                    success: function(response) {
                        if (response.success) {
                            RMCU_Admin.showToast(response.data.message, 'success');
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            }
                        } else {
                            RMCU_Admin.showToast(response.data.message, 'error');
                        }
                    },
                    error: function() {
                        RMCU_Admin.showToast('Une erreur est survenue', 'error');
                    },
                    complete: function() {
                        $submit.prop('disabled', false).text(originalText);
                    }
                });
            });
        },

        // Media Uploader WordPress
        initMediaUploader: function() {
            $('.rmcu-media-upload').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $input = $button.siblings('.rmcu-media-url');
                var $preview = $button.siblings('.rmcu-media-preview');
                
                var mediaUploader = wp.media({
                    title: 'Sélectionner une image',
                    button: {
                        text: 'Utiliser cette image'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $input.val(attachment.url);
                    if ($preview.length) {
                        $preview.html('<img src="' + attachment.url + '" style="max-width: 200px;">');
                    }
                });
                
                mediaUploader.open();
            });
            
            $('.rmcu-media-remove').on('click', function(e) {
                e.preventDefault();
                $(this).siblings('.rmcu-media-url').val('');
                $(this).siblings('.rmcu-media-preview').empty();
            });
        },

        // Color Picker
        initColorPicker: function() {
            if ($.fn.wpColorPicker) {
                $('.rmcu-color-picker').wpColorPicker();
            }
        },

        // Date Picker
        initDatePicker: function() {
            if ($.fn.datepicker) {
                $('.rmcu-date-picker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },

        // Test de connexion API
        testApiConnection: function() {
            var $button = $(this);
            $button.prop('disabled', true).text('Test en cours...');
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_test_api',
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Connexion API réussie!', 'success');
                    $('#rmcu-api-status').html('<span class="dashicons dashicons-yes-alt"></span> Connecté');
                } else {
                    RMCU_Admin.showToast('Échec de la connexion: ' + response.data.message, 'error');
                    $('#rmcu-api-status').html('<span class="dashicons dashicons-warning"></span> Déconnecté');
                }
            }).always(function() {
                $button.prop('disabled', false).text('Tester la connexion');
            });
        },

        // Export des données
        exportData: function() {
            var format = $('#rmcu-export-format').val();
            
            window.location.href = rmcu_admin.export_url + '&format=' + format + '&nonce=' + rmcu_admin.nonce;
        },

        // Import des données
        importData: function() {
            var file = this.files[0];
            if (!file) return;
            
            var formData = new FormData();
            formData.append('action', 'rmcu_import_data');
            formData.append('file', file);
            formData.append('nonce', rmcu_admin.nonce);
            
            $.ajax({
                url: rmcu_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        RMCU_Admin.showToast('Import réussi!', 'success');
                        location.reload();
                    } else {
                        RMCU_Admin.showToast('Erreur: ' + response.data.message, 'error');
                    }
                }
            });
        },

        // Synchronisation manuelle
        syncNow: function() {
            var $button = $(this);
            $button.prop('disabled', true).html('<span class="spinner is-active"></span> Synchronisation...');
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_sync_now',
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Synchronisation terminée!', 'success');
                    $('#rmcu-last-sync').text(response.data.last_sync);
                } else {
                    RMCU_Admin.showToast('Erreur de synchronisation', 'error');
                }
            }).always(function() {
                $button.prop('disabled', false).html('Synchroniser maintenant');
            });
        },

        // Nettoyage du cache
        clearCache: function() {
            if (!confirm('Êtes-vous sûr de vouloir vider le cache?')) {
                return;
            }
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_clear_cache',
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Cache vidé avec succès!', 'success');
                }
            });
        },

        // Toggle module
        toggleModule: function() {
            var $toggle = $(this);
            var module = $toggle.data('module');
            var enabled = $toggle.is(':checked');
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_toggle_module',
                module: module,
                enabled: enabled,
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Module ' + (enabled ? 'activé' : 'désactivé'), 'success');
                } else {
                    $toggle.prop('checked', !enabled);
                    RMCU_Admin.showToast('Erreur lors de la modification', 'error');
                }
            });
        },

        // Sauvegarde des paramètres
        saveSettings: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submit = $form.find('[type="submit"]');
            $submit.prop('disabled', true);
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_save_settings',
                settings: $form.serialize(),
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Paramètres sauvegardés!', 'success');
                } else {
                    RMCU_Admin.showToast('Erreur de sauvegarde', 'error');
                }
            }).always(function() {
                $submit.prop('disabled', false);
            });
        },

        // Recherche en temps réel
        liveSearch: function() {
            var query = $(this).val();
            var $results = $('#rmcu-search-results');
            
            if (query.length < 3) {
                $results.empty();
                return;
            }
            
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(function() {
                $.post(rmcu_admin.ajax_url, {
                    action: 'rmcu_live_search',
                    query: query,
                    nonce: rmcu_admin.nonce
                }, function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                    }
                });
            }, 300);
        },

        // Actions en masse
        bulkAction: function() {
            var action = $('#bulk-action-selector').val();
            var items = $('.rmcu-bulk-select:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (!action || items.length === 0) {
                alert('Veuillez sélectionner une action et au moins un élément');
                return;
            }
            
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action?')) {
                return;
            }
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_bulk_action',
                bulk_action: action,
                items: items,
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Action effectuée avec succès!', 'success');
                    location.reload();
                } else {
                    RMCU_Admin.showToast('Erreur: ' + response.data.message, 'error');
                }
            });
        },

        // Réinitialisation des paramètres
        resetSettings: function() {
            if (!confirm('Attention! Cela réinitialisera tous les paramètres. Continuer?')) {
                return;
            }
            
            $.post(rmcu_admin.ajax_url, {
                action: 'rmcu_reset_settings',
                nonce: rmcu_admin.nonce
            }, function(response) {
                if (response.success) {
                    RMCU_Admin.showToast('Paramètres réinitialisés', 'success');
                    location.reload();
                }
            });
        }
    };

    // Initialisation au chargement du DOM
    $(document).ready(function() {
        RMCU_Admin.init();
    });

})(jQuery);