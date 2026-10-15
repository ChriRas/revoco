<?php

declare(strict_types=1);

/*
| Operator panel UI strings — German.
| "Widerruf" is the § 356a BGB term, aligned with lang/de/{mail,push,wf}.php.
| Never use "Widerspruch" (a different legal concept).
*/

return [

    'settings' => [
        'navigation_group' => 'Einstellungen',
        'notification' => [
            'navigation_label' => 'Benachrichtigungen',
            'title' => 'Benachrichtigungen',
            'effective' => 'Benachrichtigungen gehen aktuell an: :email',
            'effective_none' => 'Es ist noch kein Empfänger konfiguriert — es werden keine Betreiber-Benachrichtigungen versendet.',
            'email' => [
                'label' => 'Empfängeradresse',
                'help' => 'Adresse, an die neue Widerrufe gemeldet werden. Kann sich von der Absenderadresse unterscheiden (z. B. Versand über no-reply@, Eingang an shop@). Bleibt das Feld leer, wird die Umgebungsvariable MERCHANT_NOTIFICATION_EMAIL und andernfalls die Impressum-E-Mail verwendet.',
            ],
            'spam_digest_time' => [
                'label' => 'Uhrzeit der Spam-Zusammenfassung',
                'help' => 'Täglich um diese Uhrzeit (HH:MM, Zeitzone der Anwendung) geht eine Zusammenfassung an die Empfängeradresse: die seit der letzten Zusammenfassung eingegangenen Spam- und verdächtigen Eingänge ohne Ihre Entscheidung sowie die Anzahl der automatisch aussortierten (höchstens 7 Tage zurück). Ohne solche Eingänge wird nichts versendet.',
            ],
            'test' => [
                'button' => 'Test-Mail senden',
                'sent' => 'Test-Mail an :email gesendet.',
                'failed' => 'Test-Mail konnte nicht gesendet werden.',
                'none' => 'Kein Empfänger konfiguriert — bitte zuerst eine Adresse hinterlegen oder die Impressum-E-Mail setzen.',
                'sample_subject' => 'Testbenachrichtigung (Beispiel)',
            ],
        ],
        'localization' => [
            'navigation_label' => 'Sprachen',
            'title' => 'Sprachen',
            'available' => [
                'label' => 'Angebotene Sprachen',
                'help' => 'Sprachen, die das Widerrufsformular in der Sprachauswahl anbietet. Die Auswahl wird ausgeblendet, wenn nur eine Sprache angeboten wird.',
            ],
            'default' => [
                'label' => 'Standardsprache',
                'help' => 'Wird verwendet, wenn der Verbraucher keine Sprache gewählt hat.',
                'not_available' => 'Die Standardsprache muss eine der angebotenen Sprachen sein.',
            ],
        ],
        'network' => [
            'navigation_label' => 'Netzwerk-Signale',
            'title' => 'Netzwerk-Signale',
            'description' => 'Land und Netz eines Eingangs werden auf diesem Server aus der IP-Adresse abgeleitet. Sie erhöhen nur den Spam-Wert und blockieren nie einen Eingang. Die IP-Adresse selbst wird nicht gespeichert.',
            'shipping_countries' => [
                'label' => 'Versandländer',
                'help' => 'Nur ein Signal — blockiert nie. Ein Eingang aus einem anderen Land erhöht den Spam-Wert geringfügig. Leer = Signal aus.',
            ],
            'status' => [
                'heading' => 'Datenquellen',
                'help' => 'Wird täglich aktualisiert. Ohne Daten bleiben die Netzwerk-Signale stumm.',
                'last_refresh' => 'Letzte Aktualisierung',
                'entries' => 'Einträge',
                'never' => 'nie',
                'source' => [
                    'geo' => 'Länder- und Netz-Datenbank',
                    'drop' => 'Gelistete IP-Netze',
                    'asn-drop' => 'Gelistete Netzbetreiber',
                    'tor' => 'Tor-Exit-Knoten',
                ],
            ],
            'refresh' => [
                'button' => 'Jetzt aktualisieren',
                'queued' => 'Aktualisierung eingeplant — der Queue-Worker lädt die Daten.',
            ],
            'attribution' => 'IP Geolocation by DB-IP',
        ],
        'scope' => [
            'navigation_label' => 'Widerrufsumfang',
            'title' => 'Widerrufsumfang',
            'description' => 'Legt fest, welche Vertragsarten das Formular benennt. Diese Auswahl gestaltet nur die Formulartexte — sie schränkt den Widerruf niemals ein. Verbraucher können immer per Freitext angeben, was sie widerrufen möchten.',
            'goods' => [
                'label' => 'Waren',
                'help' => 'Der Shop verkauft körperliche Waren.',
            ],
            'services' => [
                'label' => 'Dienstleistungen',
                'help' => 'Der Shop bietet Dienstleistungen an.',
            ],
            'digital' => [
                'label' => 'Digitale Inhalte',
                'help' => 'Der Shop bietet digitale Inhalte an, etwa Downloads oder Streaming.',
            ],
        ],
        'legal' => [
            'navigation_label' => 'Rechtstexte',
            'title' => 'Rechtstexte',
            'tab_privacy' => 'Datenschutzerklärung',
            'tab_imprint' => 'Impressum',
            'privacy_content' => [
                'label' => 'Datenschutzerklärung',
                'help' => 'Der eigene Datenschutztext je Sprache. Revoco liefert keine vorgefertigten Rechtstexte — für den Inhalt sind Sie als Verantwortlicher zuständig.',
            ],
            'privacy_link' => [
                'label' => 'Externe Datenschutz-URL (überschreibt)',
                'help' => 'Wenn gesetzt, verweist der Fußzeilen-Link dorthin und die interne Seite leitet dorthin weiter. Leer lassen, um den Text oben zu verwenden.',
            ],
            'fallback_order' => [
                'label' => 'Ersatzsprachen-Reihenfolge',
                'help' => 'Sprachen, die der Reihe nach herangezogen werden, wenn die angeforderte Sprache keinen Text hat.',
            ],
            'imprint_link' => [
                'label' => 'Externe Impressum-URL (überschreibt)',
                'help' => 'Wenn gesetzt, verweist der Fußzeilen-Link dorthin und die interne Seite leitet dorthin weiter. Leer lassen, um die Angaben unten zu verwenden.',
            ],
            'imprint_entity' => [
                'label' => 'Angaben zum Unternehmen',
                'help' => 'Name, Rechtsform und gesetzlicher Vertreter gemäß § 5 Abs. 1 Nr. 1 DDG.',
            ],
            'imprint_contact' => [
                'label' => 'Kontakt',
                'help' => 'E-Mail-Adresse und ein zweiter schneller Kontaktweg (§ 5 Abs. 1 Nr. 2 DDG, EuGH C-298/07).',
            ],
            'imprint_register' => [
                'label' => 'Handelsregister',
                'help' => 'Registergericht und Registernummer, sofern eingetragen (§ 5 Abs. 1 Nr. 4 DDG).',
            ],
            'imprint_tax' => [
                'label' => 'Steuerliche Angaben',
                'help' => 'Umsatzsteuer-ID (§ 27a UStG) und/oder Wirtschafts-IdNr. (§ 139c AO), sofern vorhanden (§ 5 Abs. 1 Nr. 6 DDG).',
            ],
            'imprint_professional' => [
                'label' => 'Berufsrechtliche Angaben (optional)',
                'help' => 'Nur für reglementierte Berufe und genehmigungspflichtige Tätigkeiten (§ 5 Abs. 1 Nr. 3 u. 5 DDG).',
            ],
            'imprint_addendum' => [
                'label' => 'Freier Zusatz',
                'help' => 'Freitextfeld je Sprache für weitere Pflichtangaben oder Hinweise (z. B. VSBG-Hinweis auf Anraten des Anwalts).',
            ],
            'imprint_name' => ['label' => 'Name / Firma'],
            'imprint_legal_form' => ['label' => 'Rechtsform'],
            'imprint_represented_by' => ['label' => 'Vertreten durch'],
            'imprint_address' => [
                'label' => 'Anschrift',
                'help' => 'Vollständige Postanschrift (keine Postfachadresse) nach § 5 Abs. 1 Nr. 1 DDG. Je Sprache eintragen, damit Landesbezeichnung oder andere Angaben lokalisiert werden können.',
            ],
            'imprint_email' => ['label' => 'E-Mail-Adresse'],
            'imprint_phone' => ['label' => 'Telefonnummer'],
            'imprint_contact_note' => ['label' => 'Weiterer Kontakthinweis'],
            'imprint_register_court' => ['label' => 'Registergericht'],
            'imprint_register_number' => ['label' => 'Registernummer'],
            'imprint_vat_id' => ['label' => 'Umsatzsteuer-Identifikationsnummer'],
            'imprint_business_id' => ['label' => 'Wirtschafts-Identifikationsnummer'],
            'imprint_supervisory_authority' => ['label' => 'Aufsichtsbehörde'],
            'imprint_chamber' => ['label' => 'Kammer'],
            'imprint_job_title' => ['label' => 'Berufsbezeichnung und Verleihungsstaat'],
            'imprint_professional_rules' => ['label' => 'Berufsrechtliche Regelungen und Fundstelle'],
            'imprint_liquidation_note' => ['label' => 'Abwicklungs-/Insolvenzhinweis (§ 5 Nr. 7 DDG)'],
        ],
    ],

    'editor' => [
        'paste_html' => [
            'tool' => 'HTML einfügen',
            'heading' => 'HTML einfügen',
            'description' => 'HTML hier einfügen (z. B. eine von einer Kanzlei gelieferte Datenschutzerklärung). Der Inhalt wird bereinigt und formatiert in den Editor übernommen.',
            'placeholder' => '<h2>Überschrift</h2><p>Ihr HTML-Text …</p>',
            'submit' => 'Einfügen',
        ],
    ],

    'resource' => [
        'navigation_label' => 'Widerrufe',
        'model_label' => 'Widerruf',
        'plural_model_label' => 'Widerrufe',
    ],

    'column' => [
        'received' => 'Eingegangen',
        'name' => 'Name',
        'order_number' => 'Bestellnr.',
        'spam_level' => 'Spam-Stufe',
        'handled' => 'Bearbeitet',
        'country' => 'Land',
    ],

    'filter' => [
        'handled_status' => 'Bearbeitungsstatus',
        'handled_only' => 'Nur bearbeitete',
        'unhandled_only' => 'Nur unbearbeitete',
        'spam_level' => 'Spam-Stufe',
        'signal' => 'Signal',
        'date_range' => 'Zeitraum',
        'date_from' => 'Von',
        'date_until' => 'Bis',
    ],

    'action' => [
        'mark_handled' => 'Als bearbeitet markieren',
        'handled_not_needed' => 'Spam – keine Bearbeitung nötig',
        'unmark_handled' => 'Markierung aufheben',
        'mark_spam' => 'Als Spam markieren',
        'mark_spam_heading' => 'Als Spam markieren?',
        'mark_spam_description' => 'Der Widerruf wechselt in den Reiter „Spam“ und wird in :days Tagen endgültig gelöscht. Bis dahin können Sie das mit „Kein Spam“ rückgängig machen.',
        'is_not_spam' => '„Kein Spam“ bestätigt',
        'not_spam' => 'Kein Spam',
        'not_spam_heading' => 'Als „Kein Spam“ markieren?',
        'not_spam_description' => 'Der Widerruf wechselt in den Reiter „Posteingang“ und wird nie automatisch gelöscht. Die Eingangsbestätigung geht an den Einreicher, falls sie noch nicht verschickt wurde.',
    ],

    'tab' => [
        'inbox' => 'Posteingang',
        'spam' => 'Spam',
    ],

    'statistics' => [
        'navigation_label' => 'Statistik',
        'title' => 'Statistik',
        'period' => [
            'today' => 'Heute',
            'month' => 'Monat',
            'year' => 'Jahr',
            'all' => 'Gesamt',
        ],
        'tile' => [
            'genuine' => [
                'label' => 'Echt',
                'description' => 'Widerrufe, die als echt gelten',
            ],
            'suspected' => [
                'label' => 'Verdächtig',
                'description' => 'Widerrufe mit Verdacht, noch nicht beurteilt',
            ],
            'spam' => [
                'label' => 'Spam',
                'description' => 'Als Spam bestätigt oder eingestuft',
            ],
            'auto_sorted' => [
                'label' => 'Automatisch aussortiert',
                'description' => 'Automatisch als Spam aussortiert (Teil von Spam)',
            ],
            'false_alarms' => [
                'label' => 'Fehlalarme',
                'description' => 'Rot eingestuft, dann als „Kein Spam“ freigegeben',
            ],
            'missed' => [
                'label' => 'Übersehen',
                'description' => 'Grün oder gelb eingestuft, dann als Spam markiert',
            ],
        ],
        'chart' => [
            'heading' => 'Verlauf',
            'day_format' => 'j.n.',
            'empty' => 'In diesem Zeitraum wurden keine Widerrufe gezählt',
            'series' => [
                'genuine' => 'Echt',
                'suspected' => 'Verdächtig',
                'spam' => 'Spam',
            ],
        ],
        'signals' => [
            'heading' => 'Spam-Signale',
            'empty' => 'In diesem Zeitraum hat kein Signal ausgelöst',
            'column' => [
                'signal' => 'Signal',
                'genuine' => 'Echt',
                'suspected' => 'Verdächtig',
                'spam' => 'Spam',
                'total' => 'Gesamt',
            ],
            'note' => 'Ein Widerruf mit mehreren Signalen wird pro Signal einmal gezählt. Ein Klick auf eine Zahl öffnet die Liste mit denselben Filtern, die Liste enthält aber nur noch gespeicherte Einträge: bestätigter Spam wird nach :days Tagen gelöscht.',
        ],
    ],

    'spam_level' => [
        'green' => 'Unauffällig',
        'yellow' => 'Verdächtig',
        'red' => 'Spam',
    ],

    'spam_verdict' => [
        'spam' => 'Bestätigter Spam',
        'not_spam' => 'Kein Spam',
        'spam_auto' => 'Spam (automatisch)',
        'spam_operator' => 'Spam (Ihre Entscheidung)',
        'not_spam_operator' => 'Kein Spam (Ihre Entscheidung)',
    ],

    'spam_signal' => [
        'honeypot' => 'Verstecktes Honeypot-Feld ausgefüllt',
        'throttle' => 'Zu viele Eingänge von derselben IP-Adresse',
        'render_token_invalid' => 'Formular-Token fehlt oder ist ungültig',
        'fill_time_too_fast' => 'Formular unplausibel schnell ausgefüllt',
        'user_agent_ancient' => 'Veraltete oder fehlende Browser-Kennung',
        'content_gibberish' => 'Name oder Gegenstand wirkt zufällig',
        'name_without_space' => 'Name ohne Leerzeichen',
        'field_lengths_equal' => 'Mehrere Felder mit identischer Länge',
        'country_outside_shipping' => 'Land außerhalb der Versandländer',
        'drop_network' => 'IP-Adresse in einem gelisteten Netz',
        'asn_drop' => 'Gelisteter Netzbetreiber',
        'tor_exit' => 'Tor-Exit-Knoten',
        'learned_network' => 'Netz mit wiederholt bestätigtem Spam',
        'abused_address' => 'Adresse aus bestätigtem Spam bekannt',
    ],

    'setup' => [
        'warning' => 'Einrichtung ausstehend — folgende Seiten fehlen noch: :pages.',
        'link' => 'Rechtstexte einrichten',
        'page_imprint' => 'Impressum',
        'page_privacy' => 'Datenschutzerklärung',
    ],

    'preview' => [
        'heading' => 'Vorschau',
        'empty' => 'Keine Widerrufe in diesem Reiter.',
    ],

    'infolist' => [
        'section' => [
            'submitter' => 'Angaben zum Einreicher',
            'statement' => 'Widerrufserklärung',
            'triage' => 'Triage',
            'status' => 'Status & Zeitstempel',
        ],
        'field' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'order_number' => 'Bestell-/Vertragsnummer',
            'locale' => 'Sprache',
            'subject' => 'Betreffende Ware / Dienstleistung',
            'spam_level' => 'Spam-Stufe',
            'spam_reason' => 'Spam-Grund',
            'spam_verdict' => 'Ihre Entscheidung',
            'spam_verdict_at' => 'Entscheidung am',
            'country' => 'Land',
            'network' => 'Netz',
            'handled_at' => 'Bearbeitet am',
            'received_at' => 'Eingegangen am',
            'last_updated' => 'Zuletzt aktualisiert',
        ],
        'not_handled' => 'Nicht bearbeitet',
    ],

];
