# **TERRApp: Ecosistema Digital para la Agricultura Urbana en Sudamérica**

Versión: 1.4  
Autores Intelectuales: Xavier Aguirreal (Impulsor de IA Social) & Mauricio Navarro (Téc. Agrónomo y Militante Agroecológico) Slogan: "Inteligencia nativa para tu suelo"  
Estado: Especificación para Desarrollo  
Enfoque: Soberanía Alimentaria y Tecnología Humanista

## **1\. Introducción y Manifiesto Fundacional**

### **1.1. Origen y Filosofía: La IA Social**

**TERRApp** es el resultado de la fusión estratégica entre la visión tecnológica humanista de **Xavier Aguirreal**, Impulsor de IA Social, y la praxis militante de **Mauricio Navarro**, Técnico Agrónomo y luchador por la agroecología y el cuidado del hábitat.

Este proyecto rechaza el modelo extractivista de datos y adopta un enfoque ético:

*"La inteligencia artificial por sí sola es un motor sin rumbo; carece de contexto, de ética y de sensibilidad. Mi visión es la IA Social: un modelo donde la tecnología no reemplaza al humano, sino que es necesaria y obligatoriamente asistida por mí y por las comunidades para potenciar la justicia, la dignidad y el bien común. No desarrollo algoritmos para automatizar procesos vacíos, sino para fortalecer la defensa de los Derechos Humanos, asegurar la Soberanía Alimentaria y potenciar la Economía Social. En mi trabajo, la tecnología está subordinada al criterio humano."* — Xavier Aguirreal.

Bajo esta premisa, la tecnología actúa como amplificador de la sabiduría ancestral y técnica que aporta Navarro, asegurando que cada recomendación del sistema respete los ciclos naturales y fortalezca la autonomía del usuario.

### **1.2. Definición del Alcance Estratégico**

El mercado global de aplicaciones ("Planter", "Gardenize") opera bajo una lógica mercantil y geoclimática del Hemisferio Norte (EE.UU./Europa). Esto genera una **colonización digital** de nuestras prácticas agrícolas: nos dicen cuándo sembrar basándose en el clima de Chicago, ignorando la realidad de nuestros territorios.

**TERRApp** nace para ocupar ese vacío con una solución decolonial, adaptada a las realidades del **Neotrópico y el Cono Sur**, cubriendo desde la selva misionera hasta la estepa patagónica.

### **1.3. Estado del Arte y Competencia**

El análisis de las aplicaciones líderes revela limitaciones críticas:

* **Planter (Planificación Visual):** Rígida y binaria. No entiende la complejidad de los ecosistemas locales ni el policultivo denso.  
* **From Seed to Spoon (Knowledge Hub):** Falla crítica en calendarios (Primavera en Marzo). Confunde al usuario del sur.  
* **Gardenize (Bitácoras):** "Caja vacía" que exige trabajo al usuario sin devolver inteligencia.  
* **Soluciones Locales (INTA):** Poseen la data correcta, pero carecen de la interfaz moderna necesaria para masificar la agroecología en nuevas generaciones.

### **1.4. La Brecha Regional (El Problema a Resolver)**

1. **La "Inversión Hemisférica":** Las apps globales ignoran que en el sur, Julio es invierno. TERRApp invierte los ciclos automáticamente para devolver la soberanía temporal al usuario.  
2. **Disonancia Climática:** En el NOA/NEA rigen los pisos térmicos y lluvias; en la Patagonia, el frío y el viento. La app se adapta dinámicamente.  
3. **Desconexión de Insumos:** El usuario local requiere biopreparados y economía circular, no enlaces de consumo masivo internacional.

## **2\. Arquitectura de la Solución Propuesta**

El sistema se divide en dos componentes principales:

1. **Backend Administrativo ("El Cerebro Ético"):** Panel de control donde la potencia de la IA es supervisada y validada por el expertise humano del Técnico Agrónomo.  
2. **Aplicación Móvil ("La Herramienta de Campo"):** Interfaz que empodera al usuario con decisiones agronómicas precisas basadas en su contexto real.

## **3\. Especificación Funcional: Backend Administrativo (AI-First \+ Human Validated)**

El backend tiene un único mandato: **Utilizar la IA para procesar datos masivos, pero someterlos al filtro ético y técnico humano antes de llegar al usuario.**

### **3.1. Flujo de Carga de Datos ("El Gatekeeper")**

1. **Input Mínimo:** El Técnico (Navarro) ingresa Nombre Común y Región.  
2. **Generación AI (OpenAI API):** El sistema propone una ficha técnica base (resistencia, ciclos, asociaciones).  
3. **Intervención Humana (IA Social):**  
   * La IA puede sugerir un pesticida químico por "eficiencia".  
   * **El Técnico corrige:** Elimina la sugerencia química y la reemplaza por una práctica agroecológica de manejo de hábitat. *La IA aprende de esta corrección.*  
4. **Validación de Integridad:** Checklist automático de datos climáticos.  
5. **Publicación:** Solo ocurre tras el "Visto Bueno" humano.

### **3.2. Checklist de Validación de Datos**

Para que una especie sea "Publicable" en TERRApp:

| Campo de Dato | Impacto en la App | Validación Técnica |
| :---- | :---- | :---- |
| **Rango Térmico / Pisos** | Adaptación a Altura (Norte) y Latitud (Sur). | INT (msnm) y Temp Min/Max |
| **Resistencia a Heladas** | Define necesidad de protección (Invernadero). | BOOLEAN |
| **Estrato / Capa** | Base para el policultivo (Agroecología). | ENUM |
| **Ciclo a Cosecha** | Cálculo de notificaciones. | INT (días) |
| **Req. Hídrico** | Alimenta el "Semáforo Hídrico". | INT 1-5 |
| **Asociaciones** | Fomento de biodiversidad. | Array |

## **4\. Especificación Funcional: Aplicación Móvil (Experiencia de Usuario)**

La App actúa como un intérprete de las condiciones locales para potenciar la autonomía.

### **4.1. Módulo de Sintonización (Onboarding Híbrido)**

* **Geolocalización con Sentido:**  
  * *Latitud \< \-29 (Sur):* Activa **Modo Estacional Sur** (Invierte calendarios, considera heladas y viento).  
  * *Latitud \> \-29 (Norte):* Activa **Modo Pisos Térmicos** (Prioriza altitud y lluvias).  
* **Calibración:** Cuestionario simple sobre suelo y exposición al viento.

### **4.2. Motor de Recomendación Contextual**

* Filtra especies no solo por viabilidad técnica, sino por pertinencia local.  
* *Ejemplo:* En Ushuaia, oculta cultivos tropicales inviables para evitar la frustración y el desperdicio de recursos del usuario.

### **4.3. Planificador de Estratos y Protección**

* **Interfaz:** Sistema de capas visuales que enseña a observar el ecosistema (Suelo, Cobertura, Aérea).  
* **Feature "Escudo":** Permite agregar "Cortinas de viento" o "Microtúneles", validando técnicas de protección pasiva.

### **4.4. Semáforo Hídrico y Alerta de Helada**

* **Semáforo Hídrico:** Educa sobre el uso racional del agua.  
* **Alerta de Helada:** Protección preventiva ante eventos climáticos extremos.

### **4.5. Doctor Botánico y Botiquín**

* **Enfoque Humanista:** No vende productos. Enseña a preparar remedios caseros y a entender la plaga como un desequilibrio del sistema, no como un enemigo a exterminar con químicos.

### **4.6. Gamificación**

* **Bitácora Time-Lapse:** Cámara con "Ghost Image" para documentar el proceso.  
* **Calculadora de Ahorro:** Pone en valor el trabajo del huertero estimando el ahorro económico generado.

## **5\. Especificación Técnica: Modelo de Datos (Backend)**

Estructura ajustada para soportar datos térmicos complejos y validación humana (IA Social).

\-- TABLA MAESTRA DE ESPECIES  
CREATE TABLE especies (  
    id INT PRIMARY KEY AUTO\_INCREMENT,  
    nombre\_comun VARCHAR(100),  
    nombre\_cientifico VARCHAR(100),  
    descripcion TEXT,

    \-- Lógica Híbrida (Norte y Sur)  
    piso\_termico\_min INT COMMENT 'Altitud min (Para el Norte)',  
    piso\_termico\_max INT COMMENT 'Altitud max (Para el Norte)',  
    temp\_min\_absoluta DECIMAL(4,1) COMMENT 'Temp mínima que soporta (Para el Sur)',  
    requiere\_horas\_frio BOOLEAN COMMENT 'Necesario para frutales del sur',

    estrato ENUM('raiz', 'suelo', 'herbacea', 'arbusto', 'trepadora', 'dosel'),

    \-- Datos de Cultivo  
    req\_agua TINYINT COMMENT 'Escala 1-5',  
    req\_sol TINYINT COMMENT 'Escala 1-5',  
    resistencia\_viento TINYINT COMMENT 'Escala 1-5 (Crítico Patagonia)',  
    dias\_cosecha INT,

    \-- Gestión de Contenido y Ética  
    imagen\_url VARCHAR(255),  
    estado\_publicacion ENUM('borrador', 'revision\_tecnica', 'publicado') DEFAULT 'borrador',  
    notas\_tecnicas TEXT COMMENT 'Observaciones del experto humano sobre la data de IA',  
    created\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP  
);

\-- TABLA DE ASOCIACIONES (POLICULTIVO)  
CREATE TABLE asociaciones (  
    id INT PRIMARY KEY AUTO\_INCREMENT,  
    especie\_base\_id INT,  
    especie\_asociada\_id INT,  
    tipo\_relacion ENUM('benefica', 'antagonica', 'neutra'),  
    razon\_agronomica TEXT,  
    FOREIGN KEY (especie\_base\_id) REFERENCES especies(id),  
    FOREIGN KEY (especie\_asociada\_id) REFERENCES especies(id)  
);

\-- TABLA DE REMEDIOS CASEROS (BOTIQUÍN)  
CREATE TABLE botiquin (  
    id INT PRIMARY KEY AUTO\_INCREMENT,  
    nombre VARCHAR(100),  
    ingredientes JSON,  
    instrucciones\_preparacion TEXT,  
    plagas\_target JSON,  
    es\_organico BOOLEAN DEFAULT TRUE  
);  
