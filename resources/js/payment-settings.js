document.addEventListener('DOMContentLoaded', () => {
  const bankModal = document.getElementById('bankModal');
  const deleteModal = document.getElementById('deleteBankModal');

  if (!bankModal || !deleteModal) return;

  const bankForm = document.getElementById('bankForm');
  const bankMethod = document.getElementById('bankFormMethod');
  const bankTitle = document.getElementById('bankModalTitle');
  const bankSubmitText = document.getElementById('bankSubmitText');
  const storeUrl = bankForm.action;

  const closeBankModal = () => {
    bankModal.classList.add('hidden');
    bankForm.reset();
  };

  const closeDeleteModal = () => deleteModal.classList.add('hidden');

  document.querySelector('[data-open-bank-modal]')?.addEventListener('click', () => {
    bankForm.reset();
    bankForm.action = storeUrl;
    bankMethod.value = 'POST';
    bankTitle.textContent = 'Tambah Rekening Bank';
    bankSubmitText.textContent = 'Tambah';
    document.getElementById('bank_is_active').checked = true;
    bankModal.classList.remove('hidden');
  });

  document.querySelectorAll('[data-edit-bank]').forEach(button => {
    button.addEventListener('click', () => {
      const account = JSON.parse(button.dataset.account);
      bankForm.action = button.dataset.updateUrl;
      bankMethod.value = 'PUT';
      bankTitle.textContent = 'Edit Rekening Bank';
      bankSubmitText.textContent = 'Update';
      document.getElementById('bank_name').value = account.bank_name;
      document.getElementById('account_number').value = account.account_number;
      document.getElementById('account_holder').value = account.account_holder;
      document.getElementById('bank_is_active').checked = Boolean(account.is_active);
      bankModal.classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-delete-bank]').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('deleteBankName').textContent = button.dataset.bankLabel;
      document.getElementById('deleteBankForm').action = button.dataset.deleteUrl;
      deleteModal.classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-close-bank-modal]').forEach(button => button.addEventListener('click', closeBankModal));
  document.querySelectorAll('[data-close-delete-bank-modal]').forEach(button => button.addEventListener('click', closeDeleteModal));

  bankModal.addEventListener('click', event => {
    if (event.target === bankModal) closeBankModal();
  });
  deleteModal.addEventListener('click', event => {
    if (event.target === deleteModal) closeDeleteModal();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeBankModal();
      closeDeleteModal();
    }
  });
});
