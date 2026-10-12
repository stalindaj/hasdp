{{--
    Lays a print form out as real A4-landscape pages, on screen and on paper
    alike. The form's content sits in one `.flow` container; each child is a
    block that moves onto a page whole. A `table.split` may break between its
    <tbody> groups instead — one output never straddles two pages — and the
    new page repeats the table's <thead>.

    Pair with the `.sheet` page styles (297 x 210 mm, 10 mm padding,
    @page margin 0) so what the screen shows is exactly what prints.
--}}
<script>
    window.addEventListener('load', function () {
        var flow = document.querySelector('.flow');
        if (!flow) return;

        var host = flow.parentNode;
        var page;

        function newPage() {
            page = document.createElement('div');
            page.className = 'sheet';
            host.insertBefore(page, flow);
            return page;
        }

        function fits() {
            return page.scrollHeight <= page.clientHeight + 1;
        }

        function shellOf(table) {
            var shell = table.cloneNode(false);
            if (table.tHead) shell.appendChild(table.tHead.cloneNode(true));
            return shell;
        }

        newPage();

        Array.prototype.slice.call(flow.children).forEach(function (block) {
            if (block.matches('table.split')) {
                var shell = shellOf(block);
                page.appendChild(shell);

                Array.prototype.slice.call(block.tBodies).forEach(function (body) {
                    shell.appendChild(body);
                    if (fits()) return;

                    if (shell.tBodies.length > 1) {
                        // Carry this group over, under a repeated heading.
                        shell.removeChild(body);
                        newPage();
                        shell = shellOf(block);
                        page.appendChild(shell);
                        shell.appendChild(body);
                    } else if (page.children.length > 1) {
                        // Do not strand a heading at the foot of a page.
                        page.removeChild(shell);
                        newPage();
                        page.appendChild(shell);
                    }
                });
                return;
            }

            page.appendChild(block);
            if (!fits() && page.children.length > 1) {
                page.removeChild(block);
                newPage();
                page.appendChild(block);
            }
        });

        flow.parentNode.removeChild(flow);
    });
</script>
