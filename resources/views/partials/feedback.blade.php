@if(session('success'))<div class="form-alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="form-alert error"><strong>Periksa kembali data berikut.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
