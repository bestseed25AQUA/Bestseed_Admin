{{--
    Renders a grant's opaque token as a QR image, client-side.

    The QR carries only the token — exactly what the app posts to
    /api/farmer/access/redeem — so permissions, expiry and PIN stay server-side
    and a shared code can still be revoked.
--}}
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
    $(document).on('click', '.show-qr', function() {
        const token = $(this).data('token');
        const grantId = $(this).data('grant');
        const canvas = document.getElementById('qrCanvas');

        $('#qrToken').text(token);
        $('#qrGrantId').text(grantId);

        QRCode.toCanvas(canvas, token, { width: 240, margin: 1 }, function(error) {
            if (error) {
                console.error(error);
                return;
            }
            $('#qrDownload').attr('href', canvas.toDataURL('image/png'));
            $('#qrDownload').attr('download', 'farm-access-' + grantId + '.png');
        });

        $('#qrModal').modal('show');
    });
</script>
