import express from 'express';
import cors from 'cors';
import { fileURLToPath } from 'url';
import path from 'path';
import { createProxyMiddleware } from 'http-proxy-middleware';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 8000;

// Middleware
app.use(cors());

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve static files with proper MIME types
app.use('/assets', express.static(path.join(__dirname, 'assets'), {
  setHeaders: (res, path) => {
    if (path.endsWith('.css')) {
      res.setHeader('Content-Type', 'text/css');
    } else if (path.endsWith('.js')) {
      res.setHeader('Content-Type', 'application/javascript');
    }
  }
}));

// Create SQLite database directory if it doesn't exist
const dbDir = path.join(__dirname, 'var');
if (!fs.existsSync(dbDir)) {
    fs.mkdirSync(dbDir, { recursive: true });
}

// Serve public files
app.use(express.static(path.join(__dirname, 'public')));

// Serve node_modules for Bootstrap and other dependencies
app.use('/node_modules', express.static(path.join(__dirname, 'node_modules'), {
  setHeaders: (res, path) => {
    if (path.endsWith('.css')) {
      res.setHeader('Content-Type', 'text/css');
      res.setHeader('Cache-Control', 'public, max-age=31536000');
    } else if (path.endsWith('.js')) {
      res.setHeader('Content-Type', 'application/javascript');
      res.setHeader('Cache-Control', 'public, max-age=31536000');
    }
  }
}));

// API routes for events
app.get('/api/events', (req, res) => {
  // This would normally fetch from a database
  const events = JSON.parse(fs.readFileSync(path.join(__dirname, 'data/events.json'), 'utf8'));
  
  // Filter by category if provided
  const { category } = req.query;
  if (category && category !== 'all') {
    const filteredEvents = events.filter(event => event.category === category);
    return res.json(filteredEvents);
  }
  
  res.json(events);
});

// API route for event details
app.get('/api/events/:id', (req, res) => {
  const eventId = parseInt(req.params.id);
  const events = JSON.parse(fs.readFileSync(path.join(__dirname, 'data/events.json'), 'utf8'));
  const event = events.find(e => e.id === eventId) || {
    id: eventId,
    title: 'Festival de Musique Dakar 2024',
    description: 'Le plus grand festival de musique du Sénégal réunit les meilleurs artistes locaux et internationaux.',
    date: '2024-12-15',
    time: '19:00',
    venue: 'Stade Léopold Sédar Senghor',
    address: 'Route de l\'Aéroport, Dakar, Sénégal',
    category: 'Musique',
    image: 'https://images.pexels.com/photos/1190298/pexels-photo-1190298.jpeg?auto=compress&cs=tinysrgb&w=800',
    price: 25000,
    ticketTypes: [
      { id: 1, name: 'Standard', price: 25000, available: 500 },
      { id: 2, name: 'VIP', price: 50000, available: 100 },
      { id: 3, name: 'Premium', price: 100000, available: 50 }
    ],
    organizer: {
      name: 'Dakar Events',
      logo: 'https://via.placeholder.com/150',
      description: 'Leader dans l\'organisation d\'événements au Sénégal'
    }
  };
  
  res.json(event);
});

// API route for categories
app.get('/api/categories', (req, res) => {
  const categories = [
    'Musique', 
    'Théâtre', 
    'Danse', 
    'Conférence', 
    'Sport', 
    'Festival', 
    'Exposition', 
    'Cinéma', 
    'Technologie', 
    'Business'
  ];
  
  res.json(categories);
});

// API route for payment processing
app.post('/payment/process', (req, res) => {
  const { payment_method } = req.body;
  
  // Simulate payment processing
  const paymentId = Date.now().toString();
  
  // Redirect based on payment method
  if (payment_method === 'paydunya' || payment_method === 'paydunya_card') {
    return res.json({
      success: true,
      redirect_url: `/payment/success/${paymentId}`,
      payment_id: paymentId
    });
  } else if (payment_method === 'paydunya_orange_money' || payment_method === 'paydunya_wave') {
    return res.json({
      success: true,
      message: 'Veuillez confirmer le paiement sur votre téléphone',
      payment_id: paymentId
    });
  } else {
    return res.json({
      success: true,
      payment_id: paymentId,
      bank_details: {
        bank_name: 'Banque Atlantique Sénégal',
        account_number: 'SN08 BA00 1234 5678 9012 3456 78',
        reference: `SEN-BILLETS-${paymentId}`
      }
    });
  }
});

// Create data directory and sample data if it doesn't exist
const dataDir = path.join(__dirname, 'data');
if (!fs.existsSync(dataDir)) {
  fs.mkdirSync(dataDir, { recursive: true });
  
  // Sample events data
  const sampleEvents = [
    {
      id: 1,
      title: 'Festival de Musique Dakar 2024',
      description: 'Le plus grand festival de musique du Sénégal réunit les meilleurs artistes locaux et internationaux.',
      date: '2024-12-15',
      time: '19:00',
      venue: 'Stade Léopold Sédar Senghor',
      category: 'Musique',
      image: 'https://images.pexels.com/photos/1190298/pexels-photo-1190298.jpeg?auto=compress&cs=tinysrgb&w=800',
      price: 25000
    },
    {
      id: 2,
      title: 'Conférence Tech Sénégal',
      description: 'Conférence sur les nouvelles technologies et l\'innovation en Afrique.',
      date: '2024-11-28',
      time: '09:00',
      venue: 'King Fahd Palace Hotel',
      category: 'Technologie',
      image: 'https://images.pexels.com/photos/2774556/pexels-photo-2774556.jpeg?auto=compress&cs=tinysrgb&w=800',
      price: 15000
    },
    {
      id: 3,
      title: 'Théâtre National - Pièce Classique',
      description: 'Représentation théâtrale exceptionnelle par la troupe nationale.',
      date: '2024-12-05',
      time: '20:00',
      venue: 'Théâtre National Daniel Sorano',
      category: 'Théâtre',
      image: 'https://images.pexels.com/photos/109669/pexels-photo-109669.jpeg?auto=compress&cs=tinysrgb&w=800',
      price: 8000
    },
    {
      id: 4,
      title: 'Exposition d\'Art Contemporain',
      description: 'Découvrez les œuvres des artistes sénégalais contemporains les plus talentueux.',
      date: '2024-11-15',
      time: '10:00',
      venue: 'Musée des Civilisations Noires',
      category: 'Exposition',
      image: 'https://images.pexels.com/photos/1509534/pexels-photo-1509534.jpeg?auto=compress&cs=tinysrgb&w=800',
      price: 5000
    },
    {
      id: 5,
      title: 'Match de Football - Finale',
      description: 'Finale du championnat national de football.',
      date: '2024-12-20',
      time: '16:00',
      venue: 'Stade Abdoulaye Wade',
      category: 'Sport',
      image: 'https://images.pexels.com/photos/46798/the-ball-stadion-football-the-pitch-46798.jpeg?auto=compress&cs=tinysrgb&w=800',
      price: 10000
    },
    {
      id: 6,
      title: 'Festival International de Jazz',
      description: 'Un événement musical qui réunit les meilleurs artistes de jazz du monde entier.',
      date: '2025-01-10',
      time: '18:00',
      venue: 'Place du Souvenir',
      category: 'Musique',
      image: 'https://images.pexels.com/photos/4571219/pexels-photo-4571219.jpeg?auto=compress&cs=tinysrgb&w=800',
      price: 20000
    }
  ]; 
  fs.writeFileSync(path.join(dataDir, 'events.json'), JSON.stringify(sampleEvents, null, 2));
}

// Serve HTML files for specific routes
// Catch-all route to serve the main HTML file for all routes
app.get('*', (req, res) => {
  // For PHP files, we need to proxy to a PHP server
  if (req.path === '/' || req.path === '/index.php') {
    // Serve about.html as the default page
    res.sendFile(path.join(__dirname, 'public/about.html'));
  } else if (req.path.endsWith('.php')) {
    // For other PHP files, show a message
    res.status(200).sendFile(path.join(__dirname, 'public/about.html'));
  } else if (req.path === '/login') {
    res.sendFile(path.join(__dirname, 'public/login.html'));
  } else if (req.path === '/register') {
    res.sendFile(path.join(__dirname, 'public/register.html'));
  } else if (req.path === '/events') {
    res.sendFile(path.join(__dirname, 'public/events.html'));
  } else if (req.path === '/about') {
    res.sendFile(path.join(__dirname, 'public/about.html'));
  } else if (req.path.startsWith('/events/category/')) {
    res.sendFile(path.join(__dirname, 'public/events.html'));
  } else if (req.path.match(/^\/events\/\d+$/)) {
    res.sendFile(path.join(__dirname, 'public/about.html'));
  } else if (req.path.includes('.')) {
    // If the path contains a dot (likely a file), return 404 instead of serving about.html
    res.status(404).send('File not found');
  } else {
    // For all other routes, serve about.html
    res.sendFile(path.join(__dirname, 'public/about.html'));
  }
});

// Start server
app.listen(PORT, () => {
  console.log(`Sen-Billets server running on http://localhost:${PORT}`);
});