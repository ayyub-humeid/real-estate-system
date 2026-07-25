@auth
<script>
    (function () {
        if (window.paymentEchoSubscribed) return;

        function setupPaymentEcho() {
            if (window.paymentEchoSubscribed) return;
            if (!window.Echo) return;

            window.paymentEchoSubscribed = true;

            const userId = {{ auth()->id() }};
            window.Echo.private(`App.Models.User.${userId}`)
                .listen('.payment.received', (e) => {
                    const action = new FilamentNotificationAction('view')
                        .label('View')
                        .url(e.url)
                        .button();

                    new FilamentNotification()
                        .title(e.title)
                        .body(e.body)
                        .icon('heroicon-o-currency-dollar')
                        .iconColor('success')
                        .success()
                        .actions([action])
                        .send();
                });
        }

        // Try immediately if Echo is already loaded
        setupPaymentEcho();

        // If Echo isn't ready yet, check every 100ms until it is initialized
        if (!window.paymentEchoSubscribed) {
            const checkEcho = setInterval(() => {
                if (window.Echo) {
                    setupPaymentEcho();
                    clearInterval(checkEcho);
                }
            }, 100);
        }
    })();
</script>
@endauth
