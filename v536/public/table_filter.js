function initTailwindTable(tableId, searchId, filterConfig = null, legacyFilterColIndex = null) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const searchInput = document.getElementById(searchId);
    
    // Normalize filter configurations: can be a single string ID + colIndex, or an array of { id, colIndex }
    let filterDefs = [];
    if (typeof filterConfig === 'string') {
        const el = document.getElementById(filterConfig);
        if (el) filterDefs.push({ element: el, colIndex: legacyFilterColIndex });
    } else if (Array.isArray(filterConfig)) {
        filterConfig.forEach(cfg => {
            const el = document.getElementById(cfg.id);
            if (el) filterDefs.push({ element: el, colIndex: cfg.colIndex });
        });
    }

    function applyFilters() {
        const textFilter = searchInput ? searchInput.value.toLowerCase().trim() : '';
        
        const activeFilters = filterDefs.map(f => ({
            value: f.element ? f.element.value.toLowerCase().trim() : '',
            colIndex: f.colIndex
        }));

        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;
        rows.forEach(row => {
            if (row.id === 'noResultsRow') return;
            if (row.cells.length <= 1) return; // skip 'no records' rows

            const text = row.textContent.toLowerCase();
            const matchesText = !textFilter || text.includes(textFilter);
            
            let matchesSelects = true;
            for (const f of activeFilters) {
                if (!f.value) continue;
                if (f.colIndex !== null && row.cells.length > f.colIndex) {
                    const colText = row.cells[f.colIndex].textContent.toLowerCase().trim();
                    // Match if exactly equal, or if cell text contains the filter value (e.g. "Grade 7" in "Grade 7 - Diamond")
                    if (colText !== f.value && !colText.includes(f.value)) {
                        matchesSelects = false;
                        break;
                    }
                }
            }

            const isVisible = matchesText && matchesSelects;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        // Dynamic "No matching results found" feedback row
        let noResultsRow = table.querySelector('#noResultsRow');
        if (visibleCount === 0 && rows.length > 0) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                const colSpan = table.querySelectorAll('thead th').length || 5;
                noResultsRow.innerHTML = `<td colspan="${colSpan}" class="py-8 text-center text-slate-400 text-sm"><i class="fas fa-search mr-2"></i>No matching results found</td>`;
                const tbody = table.querySelector('tbody');
                if (tbody) tbody.appendChild(noResultsRow);
            } else {
                noResultsRow.style.display = '';
            }
        } else if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
        searchInput.addEventListener('keyup', applyFilters);
    }
    filterDefs.forEach(f => {
        if (f.element) f.element.addEventListener('change', applyFilters);
    });

    // Run filter immediately to honor any initial select states
    applyFilters();

    // Setup Sorting
    const headers = table.querySelectorAll('thead th');
    headers.forEach((header, index) => {
        if(header.classList.contains('no-sort')) return;
        header.style.cursor = 'pointer';
        header.innerHTML += ' <i class="fas fa-sort text-slate-300 ml-1 text-[10px]"></i>';
        
        let asc = true;
        header.addEventListener('click', () => {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.id !== 'noResultsRow');
            
            // Reset icons
            headers.forEach(h => {
                const icon = h.querySelector('i.fa-sort, i.fa-sort-up, i.fa-sort-down');
                if(icon) { icon.className = 'fas fa-sort text-slate-300 ml-1 text-[10px]'; }
            });
            
            // Set current icon
            const currentIcon = header.querySelector('i');
            if (currentIcon) {
                currentIcon.className = asc ? 'fas fa-sort-up text-blue-500 ml-1 text-[10px]' : 'fas fa-sort-down text-blue-500 ml-1 text-[10px]';
            }

            rows.sort((a, b) => {
                const aCol = a.cells[index].textContent.trim();
                const bCol = b.cells[index].textContent.trim();
                
                // Try numeric sort
                const aNum = parseFloat(aCol.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bCol.replace(/[^0-9.-]/g, ''));
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return asc ? aNum - bNum : bNum - aNum;
                }
                return asc ? aCol.localeCompare(bCol) : bCol.localeCompare(aCol);
            });

            rows.forEach(row => tbody.appendChild(row));
            const noResultsRow = table.querySelector('#noResultsRow');
            if (noResultsRow) tbody.appendChild(noResultsRow);
            asc = !asc;
        });
    });
}
