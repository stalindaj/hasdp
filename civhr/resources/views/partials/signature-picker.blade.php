{{--
    Shared on-screen signing controls for the IPCR / IWOT sheets. The page
    supplies $base (the root-relative ".../signature" URL) and $canSign; each
    signature block renders a button calling pickSig('<slot>') / removeSig().

    Root-relative so it works on the dev server and behind the production
    subfolder alike. Nothing here ever prints.
--}}
@if ($canSign)
    <input type="file" id="sig-file" accept="image/png,image/jpeg,image/webp" style="display:none">
    <script>
        (function () {
            const token = @json(csrf_token());
            const base = @json($base);
            const input = document.getElementById('sig-file');
            let slot = null;

            window.pickSig = function (s) { slot = s; input.value = ''; input.click(); };

            // Pull the clearest message out of whatever the server returned.
            // PHP may print a warning ahead of the JSON (a failed upload with
            // no writable temp dir does exactly that), so parse from the first
            // brace rather than trusting the whole body to be JSON.
            function explain(r, body) {
                const start = body.indexOf('{');
                if (start !== -1) {
                    try {
                        const j = JSON.parse(body.slice(start));
                        if (j.errors && j.errors.signature) return j.errors.signature[0];
                        if (j.message) return j.message;
                    } catch (e) { /* not JSON after all */ }
                }
                if (/unable to create a temporary file/i.test(body)) {
                    return 'The server could not store the upload: PHP has no writable temp directory '
                        + '(set upload_tmp_dir in php.ini). No file was saved.';
                }
                if (r.status === 419) return 'Your session expired — reload the page and try again.';
                if (r.status === 413) return 'That image is too large for the server. Try one under 8 MB.';
                return 'Upload failed (' + r.status + '). Use a PNG, JPG or WEBP under 8 MB.';
            }

            input.addEventListener('change', function () {
                if (!input.files.length || !slot) return;
                const fd = new FormData();
                fd.append('signature', input.files[0]);
                fetch(base + '/' + slot, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: fd,
                }).then(function (r) {
                    if (r.ok || r.redirected) { location.reload(); return; }
                    return r.text().then(function (body) { alert(explain(r, body)); });
                }).catch(function () {
                    alert('Could not reach the server. Check your connection and try again.');
                });
            });

            window.removeSig = function (s) {
                if (!confirm('Remove this signature from the form?')) return;
                fetch(base + '/' + s, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                }).then(function (r) { if (r.ok || r.redirected) location.reload(); });
            };
        })();
    </script>
@endif
