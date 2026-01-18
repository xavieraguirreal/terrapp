# **PLAN DE TRABAJO DEFINITIVO - TERRApp**

## **Stack Tecnológico Confirmado:**

| Componente | Tecnología | Ubicación Desarrollo |
|------------|------------|---------------------|
| **Backend API** | Laravel 10+ + MySQL | XAMPP Local |
| **Admin Panel** | React + Vite + Tailwind | XAMPP Local (dev) |
| **App PWA** | React + Vite + Tailwind | Local (dev) → Remoto (testing móvil) |
| **Deploy** | FileZilla → cPanel | Solo PWA inicialmente |

---

## **FASE 1: Backend Laravel (Local en XAMPP)**

### **1.1. Setup Inicial**
- Instalar Composer (gestor de dependencias PHP)
- Crear proyecto Laravel: `composer create-project laravel/laravel terrapp-backend`
- Configurar `.env` para MySQL de XAMPP
- Configurar CORS para que React pueda consumir la API

### **1.2. Base de Datos**
- Crear migraciones para tablas:
  - `especies` (con todos los campos del documento)
  - `asociaciones` (policultivo)
  - `botiquin` (remedios caseros)
  - `users` (admin panel)
- Seeders con datos de prueba (10-15 especies)

### **1.3. API REST**
- **Endpoints públicos** (para la PWA):
  - `GET /api/especies` (filtrado por lat/long, clima)
  - `GET /api/especies/{id}` (ficha completa)
  - `GET /api/asociaciones/{id}` (compañeras de una especie)
  - `GET /api/botiquin` (remedios caseros)

- **Endpoints admin** (protegidos):
  - `POST /api/admin/especies` (crear borrador)
  - `POST /api/admin/especies/generar-ia` (llamar OpenAI)
  - `PUT /api/admin/especies/{id}` (editar/validar)
  - `PUT /api/admin/especies/{id}/publicar` (cambiar estado)

### **1.4. Integración OpenAI**
- Servicio Laravel que llama a OpenAI API
- Prompt engineering para generar fichas técnicas
- Guardar respuesta como borrador

**Resultado Fase 1:** API REST funcional en `http://localhost:8000/api`

---

## **FASE 2: Admin Panel React (Local)**

### **2.1. Setup Inicial**
- Crear proyecto: `npm create vite@latest terrapp-admin -- --template react`
- Instalar Tailwind CSS
- Configurar Axios para conectar a Laravel local

### **2.2. Autenticación**
- Login simple (Laravel Sanctum)
- Protección de rutas

### **2.3. Módulos Admin:**

**Dashboard:**
- Métricas: especies totales, publicadas, en revisión

**Gestor de Especies:**
- Formulario mínimo (nombre común + región)
- Botón "Generar con IA" → llama a `/api/admin/especies/generar-ia`
- Vista previa de datos generados por IA
- Editor completo para validación humana (corregir datos)
- Checklist de validación (campos obligatorios)
- Botón "Publicar" (solo si pasa validación)

**Gestor de Asociaciones:**
- CRUD simple de relaciones entre especies

**Gestor de Botiquín:**
- CRUD de remedios caseros

### **2.4. Deploy local:**
- Corre en `http://localhost:5173`
- Consume API de `http://localhost:8000`

**Resultado Fase 2:** Panel admin funcional para cargar y validar especies

---

## **FASE 3: PWA Multi-dispositivo (Local + Remoto)**

### **3.1. Setup Inicial**
- Crear proyecto: `npm create vite@latest terrapp-pwa -- --template react`
- Instalar Tailwind CSS
- Configurar Vite PWA Plugin
- Crear `manifest.json` (ícono, nombre, colores)

### **3.2. Arquitectura Responsive:**

**Componentes adaptativos:**
- `<Navigation>` → Hamburger (móvil) / Sidebar (desktop)
- `<SpeciesGrid>` → 1 col (móvil) / 2 col (tablet) / 4 col (desktop)
- `<SpeciesCard>` → Vertical (móvil) / Horizontal (desktop)

**Breakpoints Tailwind:**
```
sm: 640px   (móvil grande)
md: 768px   (tablet)
lg: 1024px  (desktop)
xl: 1280px  (desktop grande)
```

### **3.3. Módulos PWA MVP:**

**Onboarding (Primera vez):**
- Solicitar geolocalización
- Detectar zona (Norte/Sur según latitud -29°)
- Cuestionario: tipo de suelo, exposición viento, clima
- Guardar perfil en localStorage

**Catálogo de Especies:**
- Consumir `/api/especies?lat=X&lon=Y`
- Grid responsive de tarjetas
- Filtros: estrato, req. agua, sol
- Búsqueda por nombre

**Ficha Técnica:**
- Layout adaptativo (tabs móvil, columnas desktop)
- Secciones: cultivo, calendario, asociaciones, cuidados
- Botón "Agregar a Mi Huerta"

**Mi Huerta (básico):**
- Lista de especies guardadas (localStorage)
- Vista de estratos simplificada
- Eliminar especies

**Configuración:**
- Editar ubicación/clima
- Modo oscuro (opcional)

### **3.4. PWA Features:**
- Service Worker (cache de especies, funciona offline)
- Instalable (botón "Agregar a inicio")
- App Shell (carga rápida)

### **3.5. Testing Móvil (estrategia simple):**

**Opción A - Mismo WiFi (más simple):**
1. Tu PC con XAMPP tiene IP local: `192.168.1.X`
2. En Laravel `.env` permitir acceso desde red local
3. Compilar PWA apuntando a `http://192.168.1.X:8000/api`
4. Subir build de PWA a cPanel con FileZilla
5. Abrir desde celular: `tudominio.com/app`
6. La PWA consume API de tu PC local

**Opción B - Túnel (para testing fuera de casa):**
1. Instalar ngrok: `ngrok http 8000`
2. Te da URL pública temporal: `https://abc123.ngrok.io`
3. PWA apunta a esa URL
4. Subir build de PWA a cPanel
5. Funciona desde cualquier móvil con internet

**Resultado Fase 3:** PWA instalable funcionando en móvil/tablet/desktop

---

## **FASE 4: Deploy Remoto (Producción)**

### **4.1. Backend Laravel en cPanel:**
- Subir código por FileZilla o Git
- Configurar base de datos MySQL remota
- Configurar `.env` de producción
- Apuntar dominio/subdominio a carpeta `public`

### **4.2. Admin Panel:**
- Compilar: `npm run build`
- Subir carpeta `dist/` a `/admin` en cPanel
- Actualizar URL de API en el código (variable de entorno)

### **4.3. PWA:**
- Compilar: `npm run build`
- Subir carpeta `dist/` a `/app` en cPanel
- Actualizar URL de API
- Configurar SSL (Let's Encrypt en cPanel)
- Testear instalación en móviles

**Resultado Fase 4:** Sistema completo en producción

---

## **Estructura de Carpetas Remota (cPanel):**

```
public_html/
├── api/                    # Laravel (carpeta public/)
│   ├── index.php
│   └── ...
├── admin/                  # Build de React Admin
│   ├── index.html
│   ├── assets/
│   └── ...
├── app/                    # Build de PWA
│   ├── index.html
│   ├── manifest.json
│   ├── sw.js (service worker)
│   └── assets/
└── terrapp-backend/        # Código Laravel (fuera de public)
    ├── app/
    ├── database/
    └── ...
```

---

## **Cronograma Estimado (sin fechas, solo orden):**

1. **Backend Laravel** → Base sólida, probarlo con Postman
2. **Admin Panel** → Cargar 20-30 especies de prueba
3. **PWA MVP** → Onboarding + Catálogo + Fichas
4. **Testing móvil** → Subir PWA, testear en dispositivos reales
5. **Iteración** → Ajustar UX según feedback
6. **Deploy completo** → Subir todo a producción

---

## **Próximos Pasos Inmediatos:**

Si aprobás este plan, empezamos por:

1. ✅ **Verificar entorno local:**
   - XAMPP funcionando (Apache + MySQL)
   - Composer instalado
   - Node.js instalado (para React)

2. ✅ **Crear proyecto Laravel**
3. ✅ **Crear base de datos y migraciones**
4. ✅ **API básica funcionando**

---

## **Notas Importantes:**

### **Desarrollo Local (Mayoría del tiempo):**
- Backend y Admin Panel se desarrollan y prueban 100% en XAMPP local
- No necesitas subir nada al remoto hasta estar listo

### **Testing PWA en Móvil:**
- La PWA se puede testear localmente en navegador (modo responsive)
- Para testing en dispositivo real: subir solo el build de la PWA a cPanel
- La PWA puede consumir tu API local (mismo WiFi) o usar ngrok

### **Deploy Final:**
- Solo cuando todo esté probado y funcionando
- FileZilla para subir archivos
- Sin complicaciones de CI/CD ni Docker

---

**Fecha de creación:** 2026-01-16
**Versión:** 1.0
**Autores:** Xavier Aguirreal & Mauricio Navarro
