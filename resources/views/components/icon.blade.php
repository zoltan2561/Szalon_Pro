@props(['name'])
<span {{ $attributes->class(['icon']) }} style="--icon:url('{{ asset('assets/icons/'.$name.'.svg') }}')" aria-hidden="true"></span>
