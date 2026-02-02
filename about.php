<?php $page_title = 'About Us'; ?>
<?php include './include/header.php'; ?>

<main>
  <section class="hero-section">
    <div class="hero-content">
    <h1>About Us</h1>
    <p style="font-weight: bolder ">Providing Quality Healthcare Services Since 1995</p><br>
    <p>Welcome to Archana clinic, where we prioritize your well-being above all else. Our team of highly-trained healthcare professionals is dedicated to providing you with the best possible care, tailored to your unique needs and preferences.</p>
    <p>Our clinic offers a wide range of services, from preventative care and health screenings to chronic disease management and specialized treatments. We use appropriate medical technology and techniques to diagnose and treat a variety of health conditions.</p>
    </div>
    <div class="mission-vision-container">
      <div class="mission-section">
        <h2>Our Mission</h2>
        <p>At Archana clinic, we are committed to providing comprehensive, compassionate, and quality healthcare services to our community. Our mission is to ensure that every patient receives personalized care in a comfortable and professional environment.</p>
      </div>
      
      <div class="vision-section">
        <h2>Our Vision</h2>
        <p>To be the leading healthcare provider in the region, known for excellence in medical care, innovative treatments, and exceptional patient experience.</p>
      </div>
    </div>
  </section>

  <section class="services-section">
    <div class="services-header">OUR SERVICES</div>
    <h1 class="services-title">Comprehensive Healthcare Solutions</h1>
    
    <div class="services-grid">
            <div class="service-card">
                <div class="service-icon general">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="service-title">General Physician</h3>
                <p class="service-description">Along with Dermatology, Gynecology, Cardiology, and Rheumatology consultation patients can also consult our physician for General, Diabetic, and Thyroid clinic.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon pediatrics">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h3 class="service-title">Pediatrics</h3>
                <p class="service-description">Pediatrics is the branch of medicine that deals with diagnosing and treating a child after birth.</p>
            </div>
            
            <div class="service-card">
                <div class="service-icon cardiology">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h3 class="service-title">Cardiology</h3>
                <p class="service-description">Cardiology is a branch of internal medicine dealing with heart and Blood vessel issues.</p>
            </div>


            
            <!-- Radiology -->
            <div class="service-card">
                <div class="service-icon">
                    <svg class="icon-green" width="60" height="60" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h10v2H7V7zm0 4h10v2H7v-2zm0 4h7v2H7v-2z"/>
                    </svg>
                </div>
                <h3 class="service-title">Radiology</h3>
                <p class="service-description">Radiology is the science that uses medical imaging to diagnose and sometimes also treat diseases within the body.</p>
            </div>

            <!-- Gynecology -->
            <div class="service-card">
                <div class="service-icon">
                    <svg class="icon-orange" width="60" height="60" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM12 7C14.2 7 16 8.8 16 11V14H8V11C8 8.8 9.8 7 12 7ZM8 16H16V18C16 19.1 15.1 20 14 20H10C8.9 20 8 19.1 8 18V16Z"/>
                    </svg>
                </div>
                <h3 class="service-title">Gynecology</h3>
                <p class="service-description">Gynecology and Obstetrics is the branch of medicine that primarily focuses on women's care during pregnancy and childbirth.</p>
            </div>

            <!-- Pharmacy -->
            <div class="service-card">
                <div class="service-icon">
                    <svg class="icon-red" width="60" height="60" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"/>
                        <circle cx="7" cy="17" r="2"/>
                        <circle cx="17" cy="17" r="2"/>
                        <path d="M7 15h10v4H7z"/>
                    </svg>
                </div>
                <h3 class="service-title">Pharmacy</h3>
                <p class="service-description">Pharmacy is the science and practice of discovering, producing, preparing, reviewing and monitoring medicine.</p>
            </div>
     
        </div>
    </section>
  

  <div class="container">
    <h2 style="color:#0c74a6; margin-top:50px;">Meet Our Team</h2>
    <?php include './include/doctors-list.php'; ?>
  </div>
</main>

<section class="contact-container">
  <?php include './include/contact-section.php'; ?>
</section>

<?php include './include/footer.php'; ?>
