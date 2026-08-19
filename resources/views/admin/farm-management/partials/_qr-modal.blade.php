{{-- One modal reused by every "show QR" button on the page. --}}
<div class="modal fade" id="qrModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Access QR Code</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <canvas id="qrCanvas"></canvas>
                <p class="mt-3 mb-1"><small class="text-muted">Access code #<span id="qrGrantId"></span></small></p>
                <p class="mb-0"><code id="qrToken" style="font-size:11px"></code></p>
                <p class="mt-3 mb-0 text-muted">
                    <small>The holder scans this in the app, then types the PIN to activate their access.</small>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                <a id="qrDownload" class="btn btn-primary" download="farm-access-qr.png">Download PNG</a>
            </div>
        </div>
    </div>
</div>
