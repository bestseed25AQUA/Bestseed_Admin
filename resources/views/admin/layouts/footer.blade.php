<footer class="footer">
  <div class="d-sm-flex justify-content-center justify-content-sm-between">
    <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">
      © 2025
    </span>
    <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">
      Hand-crafted by Saiprakash
    </span>
  </div>
</footer>
<!-- partial -->

</div> <!-- end content-wrapper -->
</div> <!-- end main-panel -->
</div> <!-- end page-body-wrapper -->


<!-- plugins:js -->
<script src="{{ asset('admin_assets/vendors/js/vendor.bundle.base.js') }}"></script>
<script src="{{ asset('admin_assets/vendors/js/vendor.bundle.addons.js') }}"></script>
<!-- endinject -->

<!-- inject:js -->
<script src="{{ asset('admin_assets/js/off-canvas.js') }}"></script>
<script src="{{ asset('admin_assets/js/hoverable-collapse.js') }}"></script>
<script src="{{ asset('admin_assets/js/misc.js') }}"></script>
<script src="{{ asset('admin_assets/js/settings.js') }}"></script>
<script src="{{ asset('admin_assets/js/todolist.js') }}"></script>
<!-- endinject -->

<!-- Custom js for this page -->
<script src="{{ asset('admin_assets/js/file-upload.js') }}"></script>
<script src="{{ asset('admin_assets/js/typeahead.js') }}"></script>
<script src="{{ asset('admin_assets/js/select2.js') }}"></script>
<script src="{{ asset('admin_assets/js/dashboard.js') }}"></script>
<script src="{{ asset('admin_assets/js/data-table.js') }}"></script>
<!-- End custom js -->
<!-- FilePond JS -->
{{-- <script src="https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js"></script>
<script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script>
<script src="https://unpkg.com/filepond/dist/filepond.js"></script>

<script>
// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginImagePreview);

    const fileInputs = document.querySelectorAll('input[type="file"].filepond');

    fileInputs.forEach(input => {
        FilePond.create(input, {
            allowMultiple: false,
            maxFileSize: '2MB',
            acceptedFileTypes: ['image/*'],
            instantUpload: false, // <--- important
            labelIdle: 'Drag & Drop your file or <span class="filepond--label-action">Browse</span>',
        });
    });
});

</script> --}}
<script src="{{ asset('admin_assets/ravindra/js/filepond/filepond-plugin-file-validate-type.js') }}"></script>
<script src="{{ asset('admin_assets/ravindra/js/filepond/filepond-plugin-image-preview.js') }}"></script>
<script src="{{ asset('admin_assets/ravindra/js/filepond/filepond.js') }}"></script>

{{-- suneditor --}}
{{-- <link href="https://cdn.jsdelivr.net/npm/suneditor@latest/dist/css/suneditor.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/suneditor@latest/dist/suneditor.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/suneditor@latest/src/lang/en.js"></script> --}}


<script src="{{ asset('admin_assets/ravindra/js/suneditor/suneditor.min.js') }}"></script>
<script src="{{ asset('admin_assets/ravindra/js/suneditor/en.js') }}"></script>

<link href="{{ asset('admin_assets/ravindra/css/filepond/dist/filepond.css') }}" rel="stylesheet">
<link href="{{ asset('admin_assets/ravindra/css/filepond/dist/filepond-plugin-image-preview.css') }}" rel="stylesheet">
<link href="{{ asset('admin_assets/ravindra/css/suneditor/suneditor.min.css') }}" rel="stylesheet">

{{-- <script src="{{ asset('admin_assets/ravindra/css/filepond/filepond-plugin-image-preview.css') }}"></script>
<script src="{{ asset('admin_assets/ravindra/css/suneditor/suneditor.min.css') }}"></script> --}}

{{-- <script src="https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.js"></script> --}}
{{-- <script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.js"></script> --}}
{{-- <script src="https://unpkg.com/filepond/dist/filepond.js"></script> --}}
{{-- <link href="https://unpkg.com/filepond/dist/filepond.css" rel="stylesheet"> --}}
{{-- <link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet"> --}}

<script>
document.addEventListener('DOMContentLoaded', async function () {
    FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginImagePreview);

    document.querySelectorAll('input.filepond').forEach(async input => {
        const existingImageUrl = input.getAttribute('data-existing');
        let files = [];

        if (existingImageUrl) {
            try {
                const response = await fetch(existingImageUrl);
                const blob = await response.blob();
                const fileName = existingImageUrl.split('/').pop();
                files.push({
                    source: new File([blob], fileName, { type: blob.type }),
                    options: { type: 'local' }
                });
            } catch (e) {
                console.error("Failed to load existing image preview", e);
            }
        }

        FilePond.create(input, {
            storeAsFile: true,
            allowMultiple: false,
            credits: false,
            files: files
        });
    });
});

</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
    // Get all filepond-enabled inputs
    const fileInputs = document.querySelectorAll('input.filepond[data-file-url]');

    fileInputs.forEach(input => {
        const pond = FilePond.find(input); // Get existing pond
        const fileUrl = input.getAttribute('data-file-url');

        if (pond && fileUrl) {
            pond.addFile(fileUrl);
        }
    });
});

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sunEditorElements = document.querySelectorAll('textarea.sun-editor');
    const editors = [];

    sunEditorElements.forEach((element) => {
        const editor = SUNEDITOR.create(element, {
            width: '100%',
            height: 300,
            charCounter: true,
            lang: SUNEDITOR_LANG['en'],
            charCounterLabel: 'Characters:',
            buttonList: [
                ['undo', 'redo', 'font', 'fontSize', 'formatBlock'],
                ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
                ['fontColor', 'hiliteColor', 'textStyle'],
                ['removeFormat'],
                ['outdent', 'indent'],
                ['align', 'horizontalRule', 'list', 'lineHeight'],
                ['table'],
                ['link', 'image', 'video'],
                ['showBlocks', 'fullScreen', 'codeView', 'preview', 'print']
            ]
        });

        editors.push({ editor, element });
    });


    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', function () {
            editors.forEach(({ editor, element }) => {
                element.value = editor.getContents();
            });
        });
    });
});
</script>

<style>
    .sun-editor .se-btn {
        width: 30px;
        height: 30px;
    }

    .sun-editor .se-btn-select {
        padding: 0px 6px;
    }

    .sun-editor {
        font-family: inherit;
    }

    .sun-editor-editable {
        font-family: inherit;
    }

    .sun-editor .se-toolbar {
        font-family: inherit;
    }

    .mini-suneditor .se-btn {
        width: 20px;
        height: 20px;
    }

    .mini-suneditor .se-btn-select {
        padding: 0px 4px;
    }

    .mini-suneditor .se-toolbar .se-btn-group {
        display: flex;
        flex-wrap: nowrap;
    }

    .mini-suneditor .se-toolbar .se-btn-group .se-btn {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mini-suneditor .se-toolbar .se-btn-group .se-btn:hover .se-dropdown-content {
        display: none !important;
        /* Prevent dropdown on hover */
    }

    .sun-editor .se-toolbar {
        z-index: unset;
    }
</style>



@stack('scripts')
@yield('jscodes')

</body>
</html>


{{-- <footer class="footer">
          <div class="d-sm-flex justify-content-center justify-content-sm-between">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Copyright © 2025</span>
            <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Hand-crafted By Techland IT Solutions</span>
          </div>
        </footer>
        <!-- partial -->
      </div>




      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>


  <!-- plugins:js -->
  <script src="{{asset('admin_assets/vendors/js/vendor.bundle.base.js')}}"></script>
  <script src="{{asset('admin_assets/vendors/js/vendor.bundle.addons.js')}}"></script>
  <!-- endinject -->

  <script src="{{asset('admin_assets/js/off-canvas.js')}}"></script>
  <script src="{{asset('admin_assets/js/hoverable-collapse.js')}}"></script>
  <script src="{{asset('admin_assets/js/misc.js')}}"></script>
  <script src="{{asset('admin_assets/js/settings.js')}}"></script>
  <script src="{{asset('admin_assets/js/todolist.js')}}"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
    <script src="{{asset('admin_assets/js/file-upload.js')}}"></script>
  <script src="{{asset('admin_assets/js/typeahead.js')}}"></script>
  <script src="{{asset('admin_assets/js/select2.js')}}"></script>
  <script src="{{asset('admin_assets/js/dashboard.js')}}"></script>
  <script src="{{asset('admin_assets/js/data-table.js')}}"></script>



 <!-- Before the closing </body> tag -->
@stack('scripts')
@yield('jscodes')

</body>


</html> --}}
