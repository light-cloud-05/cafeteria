<?php
namespace App\Observers;
use App\Models\Bitacora;
use Illuminate\Database\Eloquent\Model;
class RegistrarCambioCritico
{
    private function datos(array $datos): array
    {
        return array_diff_key($datos,array_flip(['password','remember_token','_token','created_at','updated_at']));
    }
    public function created(Model $modelo): void { $this->registrar($modelo,'CREAR',null,$this->datos($modelo->getAttributes())); }
    public function updated(Model $modelo): void
    {
        if ($modelo instanceof \App\Models\User && array_key_exists('password',$modelo->getChanges())) {
            $this->registrar($modelo,'CAMBIO_CONTRASENA',null,null);
        }
        $nuevos=$this->datos($modelo->getChanges());
        if(!$nuevos) return;
        $anteriores=array_intersect_key($this->datos($modelo->getRawOriginal()),$nuevos);
        $this->registrar($modelo,'ACTUALIZAR',$anteriores,$nuevos);
    }
    public function deleted(Model $modelo): void { $this->registrar($modelo,'ELIMINAR',$this->datos($modelo->getAttributes()),null); }
    private function registrar(Model $modelo,string $accion,?array $antes,?array $despues): void
    {
        $usuario=auth()->user();
        if(!$usuario || !$usuario->exists) return;
        Bitacora::create([
            'tablaAfectada'=>$modelo->getTable(),'idRegistroAfectado'=>$modelo->getKey(),'accion'=>$accion,
            'valoresAnteriores'=>$antes === null ? null : json_encode($antes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'valoresNuevos'=>$despues === null ? null : json_encode($despues,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'fechaHora'=>now(),'idUsuario'=>$usuario->getKey(),
        ]);
    }
}
