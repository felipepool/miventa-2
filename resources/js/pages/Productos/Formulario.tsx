import { useEffect, useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button, Col, Form, Row, Spinner } from 'react-bootstrap';
import { Link, useLocation, useNavigate, useOutletContext } from 'react-router-dom';
import { FormDialog, TextField } from '@/components/ui';
import { api, erroresDeValidacion, mensajeDeError } from '@/lib/http';
import { useSesion } from '@/lib/auth';
import type { OpcionCatalogo, Producto, UnidadVenta } from '@/types/catalogo';
import type { ProductosContexto } from './Index';

interface DatosProducto {
    nombre: string;
    categoria: string;
    marca: string;
    sku: string;
    codigo_barras: string;
    descripcion: string;
    unidad_venta: UnidadVenta;
    precio: string;
    costo: string;
    controla_stock: boolean;
}

export default function ProductoFormulario({ producto }: { producto?: Producto }) {
    const { opciones, onGuardado } = useOutletContext<ProductosContexto>();
    const location = useLocation();
    const navigate = useNavigate();
    const { permisos } = useSesion();
    const formId = useId();
    const formulario = useRef<HTMLFormElement>(null);
    const [datos, setDatos] = useState<DatosProducto>(() => ({
        nombre: producto?.nombre ?? '', categoria: producto?.categoria.id ?? '', marca: producto?.marca?.id ?? '',
        sku: producto?.sku ?? '', codigo_barras: producto?.codigo_barras ?? '', descripcion: producto?.descripcion ?? '',
        unidad_venta: producto?.unidad_venta ?? 'unidad', precio: producto ? String(producto.precio) : '',
        costo: producto?.costo === null || producto?.costo === undefined ? '' : String(producto.costo), controla_stock: producto?.controla_stock ?? true,
    }));
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [procesando, setProcesando] = useState(false);
    const envioEnCurso = useRef(false);
    const cerrar = () => navigate('/productos'+location.search, { replace: true });
    const opcionesActivas = (opciones: OpcionCatalogo[], seleccionada: string) => opciones.filter((opcion) => opcion.activa || opcion.id === seleccionada);

    useEffect(() => {
        if (Object.keys(errores).length) { formulario.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(); }
    }, [errores]);

    const guardar = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (envioEnCurso.current) { return; }
        envioEnCurso.current = true;
        setProcesando(true);
        setErrores({});
        setErrorGeneral('');
        const payload = {
            ...datos, marca: datos.marca || null, sku: datos.sku || null, codigo_barras: datos.codigo_barras || null,
            descripcion: datos.descripcion || null, costo: datos.costo === '' ? null : Number(datos.costo), precio: datos.precio.trim() === '' ? null : Number(datos.precio),
        };
        try {
            const respuesta = producto
                ? await api.put<{ data: Producto }>('/productos/'+producto.id, payload)
                : await api.post<{ data: Producto }>('/productos', payload);
            onGuardado(respuesta.data.data, producto ? 'actualizado' : 'creado');
            cerrar();
        } catch (exception) {
            const validacion = erroresDeValidacion(exception);
            setErrores(validacion);
            setErrorGeneral(Object.keys(validacion).length ? '' : mensajeDeError(exception));
        } finally {
            envioEnCurso.current = false;
            setProcesando(false);
        }
    };

    const actualizar = (campo: keyof DatosProducto, valor: string | boolean) => setDatos((actuales) => ({ ...actuales, [campo]: valor }));
    const categorias = opcionesActivas(opciones.datos?.categorias ?? [], datos.categoria);
    const marcas = opcionesActivas(opciones.datos?.marcas ?? [], datos.marca);
    const sinCategorias = !producto && opciones.datos && categorias.length === 0;

    return <FormDialog title={producto ? 'Editar producto' : 'Nuevo producto'} size="lg" onHide={cerrar} processing={procesando} actions={opciones.datos && !sinCategorias ? <>
        <Button variant="outline-secondary" disabled={procesando} onClick={cerrar}>Cancelar</Button>
        <Button type="submit" form={formId} disabled={procesando || !permisos.includes('gestionar_catalogo')}>
            {procesando && <Spinner as="span" animation="border" size="sm" aria-hidden="true" className="me-2" />}
            {procesando ? 'Guardando…' : producto ? 'Guardar cambios' : 'Crear producto'}
        </Button>
    </> : <Button variant="outline-secondary" onClick={cerrar}>Volver al listado</Button>}>
        {opciones.cargando && <p role="status"><Spinner as="span" animation="border" size="sm" className="me-2" aria-hidden="true" />Cargando categorías y marcas…</p>}
        {Boolean(opciones.error) && <div role="alert"><p>No se pudieron cargar las categorías y marcas. {mensajeDeError(opciones.error)}</p><Button variant="outline-secondary" onClick={opciones.recargar}>Reintentar</Button></div>}
        {sinCategorias && <div role="status"><p>Para crear tu primer producto, primero agrega una categoría activa.</p><Link className="btn btn-primary" to="/categorias/nueva">Crear categoría</Link></div>}
        {opciones.datos && !sinCategorias &&
        <form ref={formulario} id={formId} className="record-form" noValidate aria-busy={procesando} onSubmit={(event) => void guardar(event)}>
            <fieldset disabled={procesando}>
                <legend className="visually-hidden">Datos del producto</legend>
                <TextField label="Nombre" value={datos.nombre} maxLength={150} required error={errores.nombre} onChange={(event) => actualizar('nombre', event.target.value)} />
                <Row>
                    <Col md={6}><Form.Group controlId="producto-categoria" className="mb-3"><Form.Label>Categoría</Form.Label>
                        <Form.Select required value={datos.categoria} isInvalid={Boolean(errores.categoria)} onChange={(event) => actualizar('categoria', event.target.value)}>
                            <option value="">Selecciona una categoría</option>{categorias.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{opcion.activa ? '' : ' (inactiva)'}</option>)}
                        </Form.Select>{errores.categoria && <Form.Control.Feedback type="invalid">{errores.categoria}</Form.Control.Feedback>}
                    </Form.Group></Col>
                    <Col md={6}><Form.Group controlId="producto-marca" className="mb-3"><Form.Label>Marca (opcional)</Form.Label>
                        <Form.Select value={datos.marca} isInvalid={Boolean(errores.marca)} onChange={(event) => actualizar('marca', event.target.value)}>
                            <option value="">Sin marca</option>{marcas.map((opcion) => <option key={opcion.id} value={opcion.id}>{opcion.nombre}{opcion.activa ? '' : ' (inactiva)'}</option>)}
                        </Form.Select>{errores.marca && <Form.Control.Feedback type="invalid">{errores.marca}</Form.Control.Feedback>}
                    </Form.Group></Col>
                </Row>
                <Row>
                    <Col md={6}><TextField label="SKU (opcional)" value={datos.sku} maxLength={30} error={errores.sku} onChange={(event) => actualizar('sku', event.target.value)} /></Col>
                    <Col md={6}><TextField label="Código de barras (opcional)" value={datos.codigo_barras} maxLength={50} error={errores.codigo_barras} onChange={(event) => actualizar('codigo_barras', event.target.value)} /></Col>
                </Row>
                <Row>
                    <Col md={6}><TextField label="Precio de venta (CLP, IVA incluido)" type="number" min="0" step="1" inputMode="numeric" value={datos.precio} required error={errores.precio} onChange={(event) => actualizar('precio', event.target.value)} /></Col>
                    <Col md={6}><TextField label="Costo (CLP, opcional)" type="number" min="0" step="1" inputMode="numeric" value={datos.costo} error={errores.costo} onChange={(event) => actualizar('costo', event.target.value)} /></Col>
                </Row>
                <Row>
                    <Col md={6}><Form.Group controlId="producto-unidad" className="mb-3"><Form.Label>Unidad de venta</Form.Label>
                        <Form.Select value={datos.unidad_venta} isInvalid={Boolean(errores.unidad_venta)} onChange={(event) => actualizar('unidad_venta', event.target.value as UnidadVenta)}>
                            <option value="unidad">Unidad</option><option value="kg">Kilogramo</option>
                        </Form.Select>{errores.unidad_venta && <Form.Control.Feedback type="invalid">{errores.unidad_venta}</Form.Control.Feedback>}
                    </Form.Group></Col>
                    <Col md={6} className="d-flex align-items-center"><Form.Check id="producto-controla-stock" type="switch" label="Controlar stock" checked={datos.controla_stock} isInvalid={Boolean(errores.controla_stock)} onChange={(event) => actualizar('controla_stock', event.target.checked)} /></Col>
                </Row>
                <TextField as="textarea" rows={3} label="Descripción (opcional)" value={datos.descripcion} maxLength={1000} error={errores.descripcion} onChange={(event) => actualizar('descripcion', event.target.value)} />
                {errorGeneral && <p className="text-danger" role="alert">{errorGeneral}</p>}
            </fieldset>
        </form>}
    </FormDialog>;
}
