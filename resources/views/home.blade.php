@extends('layouts.app')

@section('title', 'Custom Software & Websites | Ionvop')
@section('description', 'Custom websites and practical software for businesses in Tagum City, Nabunturan, and beyond.')

@section('content')
    <main id="main-content">
        <section class="site-hero" aria-labelledby="hero-title">
            <div class="site-container hero-grid">
                <div class="hero-copy">
                    <p class="eyebrow">Custom software for real-world work</p>
                    <h1 class="hero-title" id="hero-title">Make the work <span>work better.</span></h1>
                    <p class="hero-description">Custom websites and practical software that fit the way your business actually runs, from Tagum City and Nabunturan to teams further afield.</p>
                    <div class="hero-actions">
                        <a class="btn btn-secondary" href="{{ route('chat') }}">Tell me what you need</a>
                        <a class="btn btn-outline hero-secondary" href="#work">Explore selected work</a>
                    </div>
                    <div class="hero-location" aria-label="Service locations">
                        <span>Tagum City</span>
                        <span>Nabunturan</span>
                        <span>Remote projects welcome</span>
                    </div>
                </div>

                <figure class="hero-showcase">
                    <div class="showcase-topline">
                        <span>Selected build</span>
                        <span>01 / 03</span>
                    </div>
                    <div class="showcase-image-wrap">
                        <img class="showcase-image" src="{{ asset('assets/nowcast.webp') }}" alt="Nowcast weather app showing local conditions and forecast details">
                    </div>
                    <figcaption class="showcase-caption">
                        <strong>Nowcast</strong>
                        <span>Weather, made useful</span>
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="section-light section-wrap" id="services" aria-labelledby="services-title">
            <div class="site-container">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">What I build</p>
                        <h2 id="services-title">Software shaped around your workflow.</h2>
                    </div>
                    <p>From a clear online presence to a custom system, the goal is the same: solve a specific business problem and make everyday work simpler.</p>
                </div>

                <div class="service-list">
                    <div class="service-row">
                        <h3 class="service-name"><span>01</span> Business websites</h3>
                        <p>Fast, considered websites that explain what you do and give customers a straightforward way to reach you.</p>
                    </div>
                    <div class="service-row">
                        <h3 class="service-name"><span>02</span> Custom software</h3>
                        <p>Purpose-built applications for the records, inventory, attendance, and operational routines your team already manages.</p>
                    </div>
                    <div class="service-row">
                        <h3 class="service-name"><span>03</span> Workflow automation</h3>
                        <p>Practical tools to reduce repetitive tasks, keep information organized, and make existing processes easier to run.</p>
                    </div>
                </div>

                <div class="pricing-heading">
                    <div>
                        <p class="eyebrow">Straightforward starting rates</p>
                        <h2>One clear monthly plan.</h2>
                    </div>
                    <p>Local clients receive priority.</p>
                </div>

                <div class="pricing-grid">
                    <article class="price-block">
                        <p class="price-audience">Philippines · Tagum City &amp; Nabunturan</p>
                        <p class="price-first">PHP 2,000 <span class="text-base font-medium">/ first month</span></p>
                        <p class="price-following">PHP 1,000 for each succeeding month</p>
                        <div class="price-installments" aria-label="Local client installment schedule">
                            <p><span>Upon development start</span><strong>PHP 500</strong></p>
                            <p><span>Upon project delivery</span><strong>PHP 1,500</strong></p>
                        </div>
                    </article>
                    <article class="price-block">
                        <p class="price-audience">International clients</p>
                        <p class="price-first">USD 20 <span class="text-base font-medium">/ first month</span></p>
                        <p class="price-following">USD 10 for each succeeding month</p>
                        <div class="price-installments" aria-label="International client installment schedule">
                            <p><span>Upon development start</span><strong>USD 5</strong></p>
                            <p><span>Upon project delivery</span><strong>USD 15</strong></p>
                        </div>
                    </article>
                </div>

                <div class="payment-note">
                    <strong>Accepted payments</strong>
                    <span class="payment-method">GCash</span>
                    <span class="payment-method">PayPal</span>
                </div>
            </div>
        </section>

        <section class="section-dark section-wrap" id="work" aria-labelledby="work-title">
            <div class="site-container">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Selected projects</p>
                        <h2 id="work-title">Built to solve the work at hand.</h2>
                    </div>
                    <p>Each project starts with a real need and takes shape around the people and processes it serves.</p>
                </div>

                <div class="portfolio-grid">
                    <article class="card project-card">
                        <figure class="project-media">
                            <img class="project-shot project-shot--portrait" src="{{ asset('assets/nowcast.webp') }}" alt="Nowcast mobile weather application screen" loading="lazy">
                        </figure>
                        <div class="card-body">
                            <p class="project-type">Weather · AI-assisted</p>
                            <h3 class="card-title">Nowcast &ndash; AI-Assisted Weather Application</h3>
                            <p class="project-description">Local conditions, forecasts, and AI-generated weather summaries with activity suggestions. Users can also check other locations supported by Google APIs.</p>
                            <div class="card-actions">
                                <a class="project-link" href="https://github.com/ionvop/nowcast" target="_blank" rel="noopener noreferrer">View project on GitHub</a>
                            </div>
                        </div>
                    </article>

                    <article class="card project-card">
                        <figure class="project-media">
                            <img class="project-shot" src="{{ asset('assets/inventory.webp') }}" alt="Inventory management system dashboard for DRMC Nutrition and Dietetics" loading="lazy">
                        </figure>
                        <div class="card-body">
                            <p class="project-type">Operations · Inventory</p>
                            <h3 class="card-title">Inventory Management System &ndash; DRMC Nutrition &amp; Dietetics Department</h3>
                            <p class="project-description">A custom inventory system designed around the department's actual processes and day-to-day requirements, rather than a generic tracking workflow.</p>
                            <div class="card-actions">
                                <a class="project-link" href="https://github.com/ionvop/inventory-management-system" target="_blank" rel="noopener noreferrer">View project on GitHub</a>
                            </div>
                        </div>
                    </article>

                    <article class="card project-card">
                        <figure class="project-media">
                            <img class="project-shot" src="{{ asset('assets/attendance.webp') }}" alt="Barcode-based attendance monitoring system for UMTC Accounting Education" loading="lazy">
                        </figure>
                        <div class="card-body">
                            <p class="project-type">Education · Attendance</p>
                            <h3 class="card-title">Attendance Monitoring System &ndash; UMTC Accounting Education Department</h3>
                            <p class="project-description">A local-network attendance tool that reads barcodes on student IDs, helping simplify roll calls without relying on a dependable internet connection.</p>
                            <div class="card-actions">
                                <a class="project-link" href="https://github.com/ionvop/yet-another-attendance-monitoring-system" target="_blank" rel="noopener noreferrer">View project on GitHub</a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="contact-section section-wrap" id="contact" aria-labelledby="contact-title">
            <div class="site-container contact-grid">
                <div class="contact-copy">
                    <p class="eyebrow">Have a problem worth solving?</p>
                    <h2 id="contact-title">Let's make the next step clearer.</h2>
                    <p>Share what is slowing your business down. We can talk through the problem and see what kind of solution makes sense.</p>
                </div>
                <div class="contact-actions">
                    <a class="btn btn-secondary" href="{{ route('chat') }}">Start a conversation</a>
                    <a class="btn btn-outline" href="mailto:ionvop@gmail.com">Email Ionvop</a>
                </div>
            </div>
        </section>
    </main>
@endsection