# About Us Page - HTML Implementation Guide

## Quick Start HTML Structure

```html
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Siamo - Digital Forester</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="top-nav">
        <!-- Navigation content -->
    </nav>

    <main class="about-page">
        <!-- Hero Section -->
        <section class="hero-section">
            <!-- Hero content -->
        </section>

        <!-- Mission Section -->
        <section class="mission-section">
            <!-- Mission content -->
        </section>

        <!-- Impact Statistics -->
        <section class="impact-statistics">
            <!-- Statistics cards -->
        </section>

        <!-- How It Works -->
        <section class="how-it-works">
            <!-- Process steps -->
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <!-- Call-to-action -->
        </section>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <!-- Footer content -->
    </footer>
</body>
</html>
```

---

## CSS Custom Properties (Design System)

```css
:root {
    /* Colors */
    --color-primary-green: #002619;
    --color-secondary-green: #41635A;
    --color-accent-mint: #79A894;
    --color-light-mint: #BCEDD7;
    --color-text-gray: #414944;
    --color-bg-beige: #F9F9F8;
    --color-white: #FFFFFF;

    /* Typography */
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --font-weight-regular: 400;
    --font-weight-bold: 700;

    /* Spacing */
    --spacing-unit: 1rem;
    --max-content-width: 1280px;

    /* Shadows */
    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
    --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.2);
}
```

---

## Section-by-Section Implementation

### 1. HERO SECTION

```html
<section class="hero-section">
    <div class="hero-container">
        <div class="hero-content">
            <p class="hero-label">MANIFESTO 01.0</p>
            <h1 class="hero-heading">
                Ingegnerizzare un'economia circolare per l'era digitale.
            </h1>
            <p class="hero-description">
                Crediamo che la tecnologia ad alte prestazioni non debba costare il
                pianeta. Digital Forester è il ponte tra l'ingegneria di precisione e la
                tutela dell'ambiente.
            </p>
        </div>
    </div>
</section>
```

**CSS**:
```css
.hero-section {
    background-color: var(--color-bg-beige);
    background-image: radial-gradient(
        circle at 50% 50%,
        rgba(208, 227, 216, 0.4) 0%,
        transparent 70%
    );
    padding: 3rem 2rem;
    min-height: 636px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-label {
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--color-secondary-green);
    margin-bottom: 1.5rem;
}

.hero-heading {
    font-size: 3rem;
    line-height: 1.2;
    color: var(--color-primary-green);
    margin-bottom: 1.5rem;
    font-weight: var(--font-weight-bold);
}

.hero-description {
    font-size: 1.125rem;
    line-height: 1.6;
    color: var(--color-text-gray);
    max-width: 600px;
}
```

---

### 2. MISSION SECTION

```html
<section class="mission-section">
    <div class="section-container">
        <h2>La Nostra Missione</h2>
        
        <div class="mission-content">
            <div class="mission-text">
                <p class="mission-statement">
                    Ogni anno vengono scartati milioni di tonnellate di elettronica,
                    eppure la maggior parte mantiene il 90% della sua utilità.
                    La nostra missione è intercettare questo flusso di rifiuti,
                    ricondizionando e ricollocando dispositivi premium con la cura
                    di un artigiano digitale.
                </p>

                <div class="mission-pillars">
                    <div class="pillar">
                        <h3>Carbon Neutral</h3>
                        <p>Ogni transazione è compensata tramite riforestazione certificata.</p>
                    </div>
                    <div class="pillar">
                        <h3>Integrità Tecnica</h3>
                        <p>Rigorosi piani tecnici in 50 punti per ogni dispositivo.</p>
                    </div>
                </div>

                <p class="conservation-text">
                    Non siamo solo un marketplace; siamo un impegno di conservazione.
                    Estendendo il ciclo di vita del silicio e dei minerali rari,
                    riduciamo la domanda di estrazioni distruttive e di produzione
                    ad alta intensità di carbonio.
                </p>
            </div>

            <div class="mission-visual">
                <!-- Image or visual element here -->
                <img src="mission-visual.png" alt="Mission visualization">
            </div>
        </div>
    </div>
</section>
```

**CSS**:
```css
.mission-section {
    background-color: var(--color-white);
    padding: 4rem 2rem;
}

.mission-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.mission-text h3 {
    font-size: 1.25rem;
    color: var(--color-primary-green);
    margin-bottom: 0.5rem;
    font-weight: var(--font-weight-bold);
}

.mission-pillars {
    display: flex;
    gap: 2rem;
    margin: 2rem 0;
}

.pillar {
    flex: 1;
}

.pillar p {
    font-size: 0.95rem;
    color: var(--color-text-gray);
    line-height: 1.6;
}

@media (max-width: 768px) {
    .mission-content {
        grid-template-columns: 1fr;
    }

    .mission-pillars {
        flex-direction: column;
    }
}
```

---

### 3. IMPACT STATISTICS SECTION

```html
<section class="impact-statistics">
    <div class="section-container">
        <h2>IMPATTO</h2>

        <div class="stats-grid">
            <!-- Large Card -->
            <div class="stat-card stat-card--large">
                <div class="stat-metric">RIFIUTI CUMULATIVI RISPARMIATI</div>
                <div class="stat-number">142k</div>
                <div class="stat-description">
                    Chilogrammi di rifiuti elettronici sottratti alle discariche.
                </div>
            </div>

            <!-- Small Cards -->
            <div class="stat-card stat-card--small">
                <div class="stat-metric">ALBERI PIANTATI</div>
                <div class="stat-number">12,400+</div>
                <div class="stat-description">
                    Ripristino della biodiversità in biomi forestali critici.
                </div>
            </div>

            <div class="stat-card stat-card--small">
                <div class="stat-metric">CO2 COMPENSATA</div>
                <div class="stat-number">3.8M</div>
                <div class="stat-description">
                    Tonnellate di emissioni di carbonio evitate attraverso il riutilizzo.
                </div>
            </div>

            <!-- Medium Card -->
            <div class="stat-card stat-card--medium">
                <div class="stat-metric">CONSERVAZIONE REGIONALE</div>
                <div class="stat-number">+9</div>
                <div class="stat-description">
                    Espandiamo la nostra impronta in 12 paesi.
                </div>
            </div>
        </div>
    </div>
</section>
```

**CSS**:
```css
.impact-statistics {
    background-color: var(--color-white);
    padding: 4rem 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.stat-card {
    padding: 2rem;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-card--large {
    background-color: var(--color-accent-mint);
    color: var(--color-white);
    grid-column: span 2;
    grid-row: span 2;
}

.stat-card--small {
    background-color: var(--color-white);
    border: 1px solid var(--color-bg-beige);
}

.stat-card--medium {
    background-color: var(--color-accent-mint);
    color: var(--color-white);
    grid-column: span 1;
    grid-row: span 1;
}

.stat-metric {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: var(--font-weight-bold);
    margin-bottom: 1rem;
    opacity: 0.8;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: var(--font-weight-bold);
    margin-bottom: 1rem;
    line-height: 1;
}

.stat-description {
    font-size: 0.875rem;
    line-height: 1.5;
    opacity: 0.9;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card--large,
    .stat-card--medium {
        grid-column: span 1;
        grid-row: span 1;
    }
}
```

---

### 4. HOW IT WORKS SECTION

```html
<section class="how-it-works">
    <div class="section-container">
        <h2>Il Ciclo della Sostenibilità</h2>
        <p class="section-tagline">Semplice, trasparente e tecnicamente impeccabile.</p>

        <div class="steps-container">
            <div class="step-card">
                <div class="step-number">01</div>
                <h3 class="step-title">Vendi</h3>
                <p class="step-description">
                    Inviaci il tuo hardware datato. Forniamo valutazioni immediate
                    e distruzione sicura dei dati come protocollo standard.
                </p>
            </div>

            <div class="step-connector"></div>

            <div class="step-card">
                <div class="step-number">02</div>
                <h3 class="step-title">Riqualifica</h3>
                <p class="step-description">
                    I nostri tecnici eseguono aggiornamenti chirurgici, sostituendo
                    batterie e componenti termici per superare le specifiche originali.
                </p>
            </div>

            <div class="step-connector"></div>

            <div class="step-card">
                <div class="step-number">03</div>
                <h3 class="step-title">Acquista</h3>
                <p class="step-description">
                    Acquista dispositivi ad alte prestazioni a una frazione del costo —
                    monetario e ambientale. Pronti per una seconda vita.
                </p>
            </div>
        </div>
    </div>
</section>
```

**CSS**:
```css
.how-it-works {
    background-color: var(--color-white);
    padding: 4rem 2rem;
}

.steps-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-top: 3rem;
    align-items: start;
}

.step-card {
    text-align: center;
    padding: 2rem;
    border-radius: 12px;
    background-color: rgba(121, 168, 148, 0.05);
    transition: all 0.3s ease;
}

.step-card:hover {
    background-color: rgba(121, 168, 148, 0.1);
    transform: translateY(-4px);
}

.step-number {
    font-size: 3.5rem;
    font-weight: var(--font-weight-bold);
    color: var(--color-primary-green);
    margin-bottom: 1rem;
    line-height: 1;
}

.step-title {
    font-size: 1.5rem;
    color: var(--color-primary-green);
    margin-bottom: 1rem;
    font-weight: var(--font-weight-bold);
}

.step-description {
    font-size: 0.95rem;
    color: var(--color-text-gray);
    line-height: 1.6;
}

.step-connector {
    grid-column: auto;
    height: 4px;
    background-color: var(--color-accent-mint);
    align-self: center;
    margin: 2rem 0;
}

@media (max-width: 768px) {
    .steps-container {
        grid-template-columns: 1fr;
    }

    .step-connector {
        height: 2px;
        margin: 1rem 0;
    }
}
```

---

### 5. CTA SECTION

```html
<section class="cta-section">
    <div class="cta-content">
        <h2>Pronto a unirti alla foresta?</h2>
        <p class="cta-quote">
            La tecnologia è una risorsa, non un bene usa e getta.
        </p>
        <div class="cta-buttons">
            <a href="/marketplace" class="btn btn--primary">
                Sfoglia il Marketplace
            </a>
            <a href="/recycle" class="btn btn--secondary">
                Ricicla un Dispositivo
            </a>
        </div>
    </div>
</section>
```

**CSS**:
```css
.cta-section {
    background-color: var(--color-accent-mint);
    color: var(--color-white);
    padding: 4rem 2rem;
    text-align: center;
    min-height: 490px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cta-section h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    font-weight: var(--font-weight-bold);
}

.cta-quote {
    font-size: 1.125rem;
    font-style: italic;
    margin-bottom: 2rem;
    opacity: 0.95;
}

.cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 1rem 2rem;
    border-radius: 8px;
    font-weight: var(--font-weight-bold);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.btn--primary {
    background-color: var(--color-primary-green);
    color: var(--color-white);
}

.btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn--secondary {
    background-color: transparent;
    color: var(--color-white);
    border: 2px solid var(--color-white);
}

.btn--secondary:hover {
    background-color: var(--color-white);
    color: var(--color-accent-mint);
}
```

---

## Responsive Breakpoints

```css
/* Mobile First Approach */

/* Small devices (landscape phones) - 576px */
@media (min-width: 576px) {
    /* Adjustments */
}

/* Tablets - 768px */
@media (min-width: 768px) {
    .mission-content {
        grid-template-columns: 1fr 1fr;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .steps-container {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Small desktops - 992px */
@media (min-width: 992px) {
    .section-container {
        max-width: var(--max-content-width);
    }
}

/* Large desktops - 1200px */
@media (min-width: 1200px) {
    body {
        font-size: 1rem;
    }
}
```

---

## Performance Optimization

1. **Images**: Use srcset for responsive images
```html
<img 
    srcset="mission-visual-small.png 480w,
            mission-visual-medium.png 768w,
            mission-visual-large.png 1280w"
    sizes="(max-width: 768px) 100vw,
           (max-width: 1280px) 50vw,
           1280px"
    src="mission-visual-large.png"
    alt="Mission visualization"
>
```

2. **CSS**: Minify and combine files
3. **Fonts**: Use font-display: swap for Inter
4. **Animations**: Use requestAnimationFrame for scroll animations
5. **Lazy loading**: Implement for off-screen images

---

## Accessibility Considerations

```html
<!-- Semantic HTML -->
<section aria-labelledby="mission-heading">
    <h2 id="mission-heading">La Nostra Missione</h2>
</section>

<!-- Color Contrast -->
<!-- All text meets WCAG AA standards (4.5:1 minimum) -->

<!-- Navigation -->
<nav aria-label="Primary navigation">
    <!-- Navigation items -->
</nav>

<!-- Focus Indicators -->
.btn:focus-visible {
    outline: 2px solid var(--color-primary-green);
    outline-offset: 2px;
}

<!-- Alt text for images -->
<img src="..." alt="Descriptive alt text">
```

---

## Browser Support

- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Mobile browsers (iOS Safari, Chrome Mobile)

---

## Testing Checklist

- [ ] Visual regression testing (Figma vs. HTML)
- [ ] Responsive design testing (all breakpoints)
- [ ] Cross-browser testing
- [ ] Accessibility audit (WCAG 2.1 AA)
- [ ] Performance testing (Lighthouse)
- [ ] Load time optimization
- [ ] SEO implementation (structured data)
- [ ] Form testing (if applicable)
- [ ] Mobile touch interactions

