<?php
$currentPage = 'doctors';
$pageTitle = 'Find Doctors & Specialists';
$pageDesc = 'Find verified doctors, specialists and medical professionals near you on HealthDial.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

$specializations = ['General Physician','Cardiologist','Dermatologist','Orthopedic','Pediatrician','Gynecologist','ENT Specialist','Neurologist','Dentist','Ophthalmologist','Psychiatrist','Urologist'];

$doctors = [
    ['name'=>'Dr. Amit Sharma','spec'=>'General Physician','hospital'=>'City Hospital, Mumbai','exp'=>'15 yrs','fee'=>'₹500','rating'=>4.8],
    ['name'=>'Dr. Priya Patel','spec'=>'Cardiologist','hospital'=>'Heart Care Center, Delhi','exp'=>'20 yrs','fee'=>'₹1,200','rating'=>4.9],
    ['name'=>'Dr. Rajesh Kumar','spec'=>'Orthopedic','hospital'=>'Bone & Joint Clinic, Bangalore','exp'=>'12 yrs','fee'=>'₹800','rating'=>4.7],
    ['name'=>'Dr. Sneha Reddy','spec'=>'Dermatologist','hospital'=>'Skin Care Hospital, Hyderabad','exp'=>'8 yrs','fee'=>'₹600','rating'=>4.6],
    ['name'=>'Dr. Mohammed Ali','spec'=>'Pediatrician','hospital'=>'Children Hospital, Chennai','exp'=>'18 yrs','fee'=>'₹700','rating'=>4.8],
    ['name'=>'Dr. Anita Desai','spec'=>'Gynecologist','hospital'=>'Women Care Center, Pune','exp'=>'22 yrs','fee'=>'₹1,000','rating'=>4.9],
    ['name'=>'Dr. Vikram Singh','spec'=>'ENT Specialist','hospital'=>'ENT Clinic, Jaipur','exp'=>'10 yrs','fee'=>'₹500','rating'=>4.5],
    ['name'=>'Dr. Kavitha Nair','spec'=>'Neurologist','hospital'=>'Brain & Spine Institute, Kochi','exp'=>'16 yrs','fee'=>'₹1,500','rating'=>4.7],
    ['name'=>'Dr. Suresh Rao','spec'=>'Dentist','hospital'=>'Smile Dental Clinic, Ahmedabad','exp'=>'14 yrs','fee'=>'₹400','rating'=>4.6],
    ['name'=>'Dr. Deepa Joshi','spec'=>'Ophthalmologist','hospital'=>'Eye Care Hospital, Lucknow','exp'=>'11 yrs','fee'=>'₹600','rating'=>4.5],
    ['name'=>'Dr. Arjun Menon','spec'=>'Psychiatrist','hospital'=>'Mind Wellness Center, Bangalore','exp'=>'9 yrs','fee'=>'₹1,000','rating'=>4.4],
    ['name'=>'Dr. Fatima Khan','spec'=>'General Physician','hospital'=>'Family Health Clinic, Delhi','exp'=>'7 yrs','fee'=>'₹400','rating'=>4.3],
];
?>

<section class="section" style="padding-top:120px;">
    <div class="container">
        <div class="section-head" style="text-align:center;margin-bottom:32px;">
            <h1>Find <span class="gradient-text">Doctors & Specialists</span></h1>
            <p style="color:var(--text-muted);margin-top:8px;" data-translate>Browse verified doctors across specializations</p>
        </div>

        <!-- Specialization Filter -->
        <div class="filter-pills" style="margin-bottom:24px;justify-content:center;">
            <button class="filter-pill active" onclick="filterDoctors('all',this)">All</button>
            <?php foreach ($specializations as $s): ?>
            <button class="filter-pill" onclick="filterDoctors('<?= $s ?>',this)"><?= $s ?></button>
            <?php endforeach; ?>
        </div>

        <!-- Doctor Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:16px;" id="doctorsGrid">
            <?php foreach ($doctors as $doc): ?>
            <div class="doctor-card reveal" data-spec="<?= $doc['spec'] ?>">
                <div class="doctor-avatar"><i class="fas fa-user-md"></i></div>
                <div class="doctor-info">
                    <div class="doctor-name"><?= $doc['name'] ?></div>
                    <div class="doctor-spec"><?= $doc['spec'] ?></div>
                    <div class="doctor-hospital"><i class="fas fa-hospital"></i> <?= $doc['hospital'] ?></div>
                    <div class="doctor-meta">
                        <span><i class="fas fa-briefcase"></i> <?= $doc['exp'] ?></span>
                        <span><i class="fas fa-rupee-sign"></i> <?= $doc['fee'] ?></span>
                        <span><i class="fas fa-star" style="color:#f59e0b;"></i> <?= $doc['rating'] ?></span>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                    <button class="btn btn-primary" style="font-size:12px;padding:8px 16px;" onclick="openAppointmentModal('<?= addslashes($doc['name']) ?>','<?= addslashes($doc['hospital']) ?>')">
                        <i class="fas fa-calendar-check"></i> Book
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="noDoctorResult" style="display:none;text-align:center;padding:60px;color:var(--text-muted);">
            <i class="fas fa-user-md" style="font-size:48px;opacity:0.3;margin-bottom:16px;display:block;"></i>
            <h3>No doctors found</h3>
            <p>Try selecting a different specialization.</p>
        </div>
    </div>
</section>

<!-- Appointment Modal -->
<div class="apt-modal-overlay" id="aptModal">
    <div class="apt-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3><i class="fas fa-calendar-check" style="color:var(--blue);"></i> Request Appointment</h3>
            <button onclick="document.getElementById('aptModal').classList.remove('active')" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <p style="color:var(--text-muted);font-size:var(--fs-sm);margin-bottom:20px;" id="aptDoctor"></p>
        <div class="apt-field">
            <label>Your Name</label>
            <input type="text" id="aptName" placeholder="Enter your name" />
        </div>
        <div class="apt-field">
            <label>Phone Number</label>
            <input type="tel" id="aptPhone" placeholder="Enter phone number" />
        </div>
        <div class="apt-field">
            <label>Preferred Date</label>
            <input type="date" id="aptDate" />
        </div>
        <div class="apt-field">
            <label>Brief Description</label>
            <textarea id="aptDesc" rows="3" placeholder="Describe your concern..."></textarea>
        </div>
        <button class="btn btn-primary" style="width:100%;padding:14px;" onclick="submitAppointment()">
            <i class="fas fa-paper-plane"></i> Send via WhatsApp
        </button>
    </div>
</div>

<script>
function filterDoctors(spec, el) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    const cards = document.querySelectorAll('.doctor-card');
    let found = 0;
    cards.forEach(c => {
        const show = spec === 'all' || c.dataset.spec === spec;
        c.style.display = show ? '' : 'none';
        if (show) found++;
    });
    document.getElementById('noDoctorResult').style.display = found ? 'none' : 'block';
}

function openAppointmentModal(doctor, hospital) {
    document.getElementById('aptDoctor').textContent = doctor + ' — ' + hospital;
    document.getElementById('aptModal').classList.add('active');
    document.getElementById('aptModal').dataset.doctor = doctor;
    document.getElementById('aptModal').dataset.hospital = hospital;
}

function submitAppointment() {
    const name = document.getElementById('aptName').value;
    const phone = document.getElementById('aptPhone').value;
    const date = document.getElementById('aptDate').value;
    const desc = document.getElementById('aptDesc').value;
    const doctor = document.getElementById('aptModal').dataset.doctor;
    const hospital = document.getElementById('aptModal').dataset.hospital;

    if (!name || !phone) { alert('Please enter your name and phone number.'); return; }

    const msg = `Hi, I'd like to book an appointment.\n\nDoctor: ${doctor}\nHospital: ${hospital}\nName: ${name}\nPhone: ${phone}\nDate: ${date || 'Flexible'}\nConcern: ${desc || 'General Consultation'}`;
    window.open('https://wa.me/919911660669?text=' + encodeURIComponent(msg), '_blank');
    document.getElementById('aptModal').classList.remove('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
