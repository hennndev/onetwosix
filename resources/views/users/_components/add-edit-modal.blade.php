<div id="userModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b border-gray-200">
      <h3 id="modalTitle"
          class="text-xl font-bold text-gray-900">Tambah User</h3>
    </div>
    <form id="userForm"
          method="POST"
          action="{{ route('admin.users.store') }}"
          class="p-6">
      @csrf
      <input type="hidden"
             name="_method"
             value="POST"
             id="formMethod">

      <div class="grid grid-cols-2 gap-4">
        <!-- Nama -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
          <input type="text"
                 name="name"
                 id="name"
                 required
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                 placeholder="Contoh: John Doe">
        </div>

        <!-- Email -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
          <input type="email"
                 name="email"
                 id="email"
                 required
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                 placeholder="Contoh: john@126club.com">
        </div>

        <!-- Password -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500"
                  id="passwordRequired">*</span></label>
          <input type="password"
                 name="password"
                 id="password"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                 placeholder="Minimal 8 karakter">
          <p class="text-xs text-gray-500 mt-1"
             id="passwordHint">Kosongkan jika tidak ingin mengubah password</p>
        </div>

        <!-- Phone -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon</label>
          <input type="text"
                 name="phone"
                 id="phone"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                 placeholder="08123456789">
        </div>

        <!-- Birth Date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Lahir</label>
          <input type="date"
                 name="birth_date"
                 id="birth_date"
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>

        <!-- Address -->
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Alamat</label>
          <textarea name="address"
                    id="address"
                    rows="2"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Alamat lengkap"></textarea>
        </div>

        <!-- Role -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
          <select name="role_id"
                  id="role_id"
                  required
                  onchange="handleRoleAreaVisibility()"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Pilih Role</option>
            @foreach ($roles as $role)
              <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
          </select>
        </div>

        <!-- Area -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Area <span class="text-red-500">*</span></label>
          <select name="area_id"
                  id="area_id"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Pilih Area</option>
            <option value="all" id="allAreaOption" class="hidden">Semua Area</option>
            @foreach ($areas as $area)
              <option value="{{ $area->id }}">{{ $area->name }}</option>
            @endforeach
          </select>
        </div>

        <!-- Status -->
        <div class="col-span-2">
          <label class="flex items-center gap-2">
            <input type="hidden"
                   name="is_active"
                   value="0">
            <input type="checkbox"
                   name="is_active"
                   id="is_active"
                   value="1"
                   checked
                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700">User Aktif</span>
          </label>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button"
                onclick="closeModal()"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
          Batal
        </button>
        <button type="submit"
                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
          Simpan
        </button>
      </div>
    </form>
  </div>
</div>
