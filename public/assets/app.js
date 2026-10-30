document.querySelector('#menu-toggle')?.addEventListener('click',()=>document.querySelector('#sidebar').classList.toggle('open'));
document.querySelector('#customer-select')?.addEventListener('change',event=>{const option=event.target.selectedOptions[0];document.querySelector('#pickup-address').value=option.dataset.address||'';});
document.querySelector('#print-order')?.addEventListener('click',()=>window.print());
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));
