<!-- Custom CSS -->
<style>
    /* Flash Message Container */
    .flash-messages {
        margin-top: 1px;
        margin-bottom: 1px;
    }

    /* General Alert Styles */
    .custom-alert {
        padding: 12px 16px;
        margin-bottom: 10px;
        border: 1px solid transparent;
        border-radius: 4px;
        position: relative;
        font-size: 15px;
        font-family: Arial, sans-serif;
    }

    .custom-alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .custom-alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .custom-alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeeba;
    }

    .custom-alert-primary {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    .custom-alert .close-btn {
        position: absolute;
        top: 8px;
        right: 12px;
        background: none;
        border: none;
        font-size: 18px;
        font-weight: bold;
        color: inherit;
        cursor: pointer;
        line-height: 1;
    }

    .custom-alert .close-btn:hover {
        color: #000;
    }
</style>

<!-- Flash Messages -->
<div class="flash-messages">
    @if(session()->has('success'))
        <div class="custom-alert custom-alert-success">
            {{ session('success') }}
            <button type="button" class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if(session()->has('danger'))
        <div class="custom-alert custom-alert-danger">
            {{ session('danger') }}
            <button type="button" class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if(session()->has('warning'))
        <div class="custom-alert custom-alert-warning">
            {{ session('warning') }}
            <button type="button" class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if(session()->has('primary'))
        <div class="custom-alert custom-alert-primary">
            {{ session('primary') }}
            <button type="button" class="close-btn" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif
</div>
