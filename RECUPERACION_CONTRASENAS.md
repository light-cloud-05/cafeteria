# Recuperación mediante el administrador

El sistema ya envía la recuperación al correo definido en `RECOVERY_ADMIN_EMAIL`, en lugar de enviarla al usuario que solicita recuperar su cuenta.

1. En `.env`, establece `RECOVERY_ADMIN_EMAIL` con el correo del administrador responsable.
2. Configura el envío real mediante `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` y `MAIL_FROM_ADDRESS`, usando los datos del proveedor de correo. No publiques estas credenciales.
3. Ejecuta `php artisan config:clear` después de cambiar la configuración.
4. El usuario pulsa «¿Olvidaste tu contraseña?» e introduce el correo registrado en su cuenta.
5. El administrador recibe un enlace asociado a esa cuenta. Comprueba la identidad del solicitante y le facilita el enlace por un canal privado para que elija su propia contraseña. El enlace vence según `auth.passwords.users.expire` y solo se puede utilizar una vez.

No es necesario compartir la contraseña del administrador ni enviar contraseñas por correo. Recuperar una contraseña también invalida la sesión anterior y elimina el bloqueo por intentos fallidos.

Las pruebas automatizadas verifican el destinatario de la notificación; no confirman la entrega a una bandeja de entrada. Para comprobar la entrega real, solicita una recuperación de una cuenta de prueba después de configurar SMTP.
