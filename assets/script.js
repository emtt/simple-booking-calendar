/**
 * Simple Booking Calendar - Frontend Script
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Esperar a que el DOM esté listo
    $(document).ready(function() {

        const BookingCalendar = {

            form: $('#sbc-booking-form'),
            dateInput: $('#sbc-date'),
            timeSelect: $('#sbc-time'),
            submitBtn: null,
            messageDiv: $('#sbc-message'),
            bookedTimes: [],

            /**
             * Inicializar el calendario
             */
            init: function() {
                this.submitBtn = this.form.find('button[type="submit"]');
                this.bindEvents();
                this.setMinDate();
                this.addPlaceholders();
                console.log('📅 Booking Calendar initialized');
            },

            /**
             * Vincular eventos
             */
            bindEvents: function() {
                // Evento de cambio de fecha
                this.dateInput.on('change', this.onDateChange.bind(this));

                // Evento de envío del formulario
                this.form.on('submit', this.onSubmit.bind(this));

                // Validación en tiempo real
                this.form.find('input[required], select[required]').on('blur', this.validateField);

                // Formatear teléfono
                $('#sbc-phone').on('input', this.formatPhone);
            },

            /**
             * Establecer fecha mínima (hoy)
             */
            setMinDate: function() {
                const today = new Date().toISOString().split('T')[0];
                this.dateInput.attr('min', today);
            },

            /**
             * Agregar placeholders
             */
            addPlaceholders: function() {
                $('#sbc-name').attr('placeholder', 'Ej: Juan Pérez');
                $('#sbc-phone').attr('placeholder', 'Ej: +34 600 123 456');
                $('#sbc-email').attr('placeholder', 'Ej: juan@ejemplo.com');
                $('#sbc-company').attr('placeholder', 'Ej: Mi Empresa S.L.');
            },

            /**
             * Manejar cambio de fecha
             */
            onDateChange: function(e) {
                const selectedDate = $(e.target).val();

                if (!selectedDate) return;

                this.showLoading(this.timeSelect, true);
                this.getBookedTimes(selectedDate);
            },

            /**
             * Obtener horas reservadas para una fecha
             */
            getBookedTimes: function(date) {
                $.ajax({
                    url: sbcAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sbc_get_bookings',
                        nonce: sbcAjax.nonce,
                        date: date
                    },
                    success: (response) => {
                        if (response.success) {
                            this.bookedTimes = response.data.booked_times;
                            this.updateTimeOptions();
                            this.showMessage('Horarios actualizados', 'info', 3000);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Error al obtener reservas:', error);
                        this.showMessage('Error al cargar horarios disponibles', 'error');
                    },
                    complete: () => {
                        this.showLoading(this.timeSelect, false);
                    }
                });
            },

            /**
             * Actualizar opciones de hora según disponibilidad
             */
            updateTimeOptions: function() {
                const options = this.timeSelect.find('option');

                options.each((index, option) => {
                    const $option = $(option);
                    const timeValue = $option.val();

                    if (timeValue === '') return; // Skip placeholder

                    if (this.bookedTimes.includes(timeValue)) {
                        $option.prop('disabled', true);
                        $option.text($option.text().replace(' (Reservado)', '') + ' (Reservado)');
                        $option.css('color', '#999');
                    } else {
                        $option.prop('disabled', false);
                        $option.text($option.text().replace(' (Reservado)', ''));
                        $option.css('color', '');
                    }
                });

                // Reset selección si está reservada
                if (this.bookedTimes.includes(this.timeSelect.val())) {
                    this.timeSelect.val('');
                }
            },

            /**
             * Manejar envío del formulario
             */
            onSubmit: function(e) {
                e.preventDefault();

                // Validar formulario
                if (!this.validateForm()) {
                    this.showMessage('Por favor, complete todos los campos requeridos correctamente', 'error');
                    return;
                }

                // Deshabilitar botón y mostrar loading
                this.setButtonLoading(true);

                // Recopilar datos
                const formData = {
                    action: 'sbc_save_booking',
                    nonce: sbcAjax.nonce,
                    booking_date: this.dateInput.val(),
                    booking_time: this.timeSelect.val(),
                    name: $('#sbc-name').val().trim(),
                    phone: $('#sbc-phone').val().trim(),
                    email: $('#sbc-email').val().trim(),
                    company: $('#sbc-company').val().trim()
                };

                // Enviar datos
                $.ajax({
                    url: sbcAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: (response) => {
                        if (response.success) {
                            this.showMessage(response.data.message, 'success');
                            this.resetForm();
                            this.celebrateSuccess();
                        } else {
                            this.showMessage(response.data.message, 'error');
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Error al guardar reserva:', error);
                        this.showMessage('Error al procesar la reserva. Intente nuevamente.', 'error');
                    },
                    complete: () => {
                        this.setButtonLoading(false);
                    }
                });
            },

            /**
             * Validar formulario completo
             */
            validateForm: function() {
                let isValid = true;
                const requiredFields = this.form.find('input[required], select[required]');

                requiredFields.each(function() {
                    const $field = $(this);
                    if (!$field.val() || !$field[0].checkValidity()) {
                        $field.addClass('error-field');
                        isValid = false;
                    } else {
                        $field.removeClass('error-field');
                    }
                });

                return isValid;
            },

            /**
             * Validar campo individual
             */
            validateField: function() {
                const $field = $(this);

                if ($field.val() && $field[0].checkValidity()) {
                    $field.removeClass('error-field').addClass('valid-field');
                } else if ($field.val()) {
                    $field.removeClass('valid-field').addClass('error-field');
                } else {
                    $field.removeClass('error-field valid-field');
                }
            },

            /**
             * Formatear número de teléfono
             */
            formatPhone: function() {
                let value = $(this).val().replace(/\D/g, '');

                // Limitar a 15 dígitos
                if (value.length > 15) {
                    value = value.substr(0, 15);
                }

                $(this).val(value);
            },

            /**
             * Mostrar mensaje
             */
            showMessage: function(message, type = 'info', duration = 5000) {
                this.messageDiv
                    .removeClass('success error info')
                    .addClass(type)
                    .html(message)
                    .fadeIn(300);

                // Auto-ocultar después de la duración especificada
                if (duration > 0) {
                    setTimeout(() => {
                        this.messageDiv.fadeOut(300);
                    }, duration);
                }

                // Scroll al mensaje
                $('html, body').animate({
                    scrollTop: this.messageDiv.offset().top - 100
                }, 500);
            },

            /**
             * Establecer estado de loading en el botón
             */
            setButtonLoading: function(loading) {
                if (loading) {
                    this.submitBtn
                        .prop('disabled', true)
                        .addClass('loading')
                        .data('original-text', this.submitBtn.text())
                        .text('Procesando...');
                } else {
                    this.submitBtn
                        .prop('disabled', false)
                        .removeClass('loading')
                        .text(this.submitBtn.data('original-text') || 'Reservar');
                }
            },

            /**
             * Mostrar loading en select
             */
            showLoading: function($element, show) {
                if (show) {
                    $element.prop('disabled', true).css('opacity', '0.5');
                } else {
                    $element.prop('disabled', false).css('opacity', '1');
                }
            },

            /**
             * Resetear formulario
             */
            resetForm: function() {
                this.form[0].reset();
                this.form.find('.error-field, .valid-field').removeClass('error-field valid-field');
                this.bookedTimes = [];
                this.timeSelect.find('option').prop('disabled', false).css('color', '');
            },

            /**
             * Animación de éxito
             */
            celebrateSuccess: function() {
                // Crear confetti effect (opcional)
                const container = this.form.parent();

                for (let i = 0; i < 30; i++) {
                    const confetti = $('<div class="confetti"></div>');
                    confetti.css({
                        left: Math.random() * 100 + '%',
                        animationDelay: Math.random() * 3 + 's',
                        backgroundColor: this.getRandomColor()
                    });
                    container.append(confetti);

                    setTimeout(() => confetti.remove(), 3000);
                }
            },

            /**
             * Obtener color aleatorio
             */
            getRandomColor: function() {
                const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'];
                return colors[Math.floor(Math.random() * colors.length)];
            }
        };

        // Inicializar si existe el formulario
        if ($('#sbc-booking-form').length) {
            BookingCalendar.init();
        }

    });

})(jQuery);

// Estilos para confetti (agregar al CSS o inline)
const confettiStyles = `
<style>
.confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    top: -10px;
    animation: confetti-fall 3s linear forwards;
    z-index: 1000;
}

@keyframes confetti-fall {
    to {
        transform: translateY(100vh) rotate(360deg);
        opacity: 0;
    }
}
</style>
`;

// Inyectar estilos de confetti
if (document.querySelector('#sbc-booking-form')) {
    document.head.insertAdjacentHTML('beforeend', confettiStyles);
}