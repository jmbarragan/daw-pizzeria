{{-- Usuarios del personal (G-09, RF-21), solo para el administrador: listado, búsqueda, alta, modificación y desactivación. --}}
<x-plantilla.gestion titulo="Usuarios" activa="usuarios">
    <x-gestion.ayuda>
        <p>Aquí están las cuentas de quienes trabajan en la gestión. Al dar de alta a alguien le llega un correo para
            elegir su contraseña: nadie más la conoce.</p>
        <p class="mb-0">Las cuentas no se borran, se desactivan: así se sigue sabiendo quién decidió cada pedido. Siempre
            tiene que quedar al menos un administrador activo, y nadie puede desactivar su propia cuenta.</p>
    </x-gestion.ayuda>

    <form method="GET" action="{{ route('gestion.usuarios') }}" class="d-flex flex-wrap gap-2 mb-3" role="search">
        <label for="buscar" class="visually-hidden">Buscar por nombre o correo</label>
        <input type="search" id="buscar" name="buscar" value="{{ $buscar }}" class="form-control" style="max-width: 20rem"
               placeholder="Buscar por nombre o correo…">
        <button type="submit" class="btn btn-outline-secondary">Buscar</button>
        @if ($buscar !== '')
            <a href="{{ route('gestion.usuarios') }}" class="btn btn-link">Ver todos</a>
        @endif
        <a href="{{ route('gestion.usuarios.nuevo') }}" class="btn btn-primary ms-auto">Nueva cuenta</a>
    </form>

    @if ($usuarios->isEmpty())
        <p>Ninguna cuenta coincide con la búsqueda.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden">Cuentas del personal, por nombre</caption>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($usuarios as $usuario)
                        <tr>
                            <td>{{ $usuario->nombre }}</td>
                            <td class="text-break">{{ $usuario->email }}</td>
                            <td>{{ $usuario->esAdministrador() ? 'Administrador' : 'Empleado' }}</td>
                            <td>{{ $usuario->activo ? 'Activo' : 'Desactivado' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('gestion.usuarios.editar', $usuario) }}" class="btn btn-sm btn-outline-secondary">
                                    Editar<span class="visually-hidden"> {{ $usuario->nombre }}</span>
                                </a>
                                <form method="POST" class="d-inline"
                                      action="{{ route($usuario->activo ? 'gestion.usuarios.desactivar' : 'gestion.usuarios.activar', $usuario) }}"
                                      @if ($usuario->activo) data-confirmar="¿Desactivar la cuenta de «{{ $usuario->nombre }}»? No podrá entrar hasta que se active otra vez." @endif>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" @class(['btn btn-sm', 'btn-outline-danger' => $usuario->activo, 'btn-outline-primary' => ! $usuario->activo])>
                                        {{ $usuario->activo ? 'Desactivar' : 'Activar' }}<span class="visually-hidden"> {{ $usuario->nombre }}</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $usuarios->links() }}
    @endif
</x-plantilla.gestion>
