# Wiyule Motors Website

Premium automotive parts and services website for Wiyule Motors in Blantyre, Malawi.

## 🚀 Quick Start

This is a fully functional, production-ready website that you can deploy immediately.

## 📋 Files Included

- `index.html` - Main website file (complete and ready to deploy)
- `robots.txt` - SEO configuration for search engines
- `sitemap.xml` - Sitemap for better search engine indexing
- `.htaccess` - Apache server configuration
- `404.html` - Custom error page
- `README.md` - This file

## 🎯 Features

✅ **Complete Sections:**
- Hero section with call-to-action
- Services showcase (6 automotive services)
- Comprehensive booking system
- Why Choose Us section
- About section with statistics
- Customer testimonials
- Photo gallery
- FAQ section with accordion
- Contact form
- Contact information
- Footer with quick links

✅ **Booking System:**
- Personal information collection
- Vehicle details form
- Multiple service selection
- Date & time picker
- Additional notes
- Form validation
- Confirmation modal
- WhatsApp & phone integration

✅ **SEO Optimized:**
- Structured data (Schema.org)
- Meta tags (Open Graph, Twitter Cards)
- Semantic HTML
- Mobile-responsive design
- Fast loading with CDN resources

✅ **User Experience:**
- Smooth animations (AOS)
- Mobile-friendly navigation
- WhatsApp floating button
- Interactive FAQ accordion
- Form validation
- Loading states
- Success messages

## 🔧 Before Launch Checklist

### 1. **Update Contact Information** (if needed)
Search for `+265993575111` and replace with your actual phone number in:
- Navigation booking buttons
- WhatsApp button (line 80)
- Contact section
- Footer

### 2. **Set Up Google Analytics**
Replace `G-XXXXXXXXXX` in the head section (line 18-24) with your actual Google Analytics tracking ID:
```html
<script async src="https://www.googletagmanager.com/gtag/js?id=G-YOUR-ACTUAL-ID"></script>
```

Get your tracking ID at: https://analytics.google.com/

### 3. **Update Social Media Images**
Replace the placeholder image URL in meta tags (line 9):
```html
<meta property="og:image" content="https://wiyulemotors.com/images/og-image.jpg">
```
Create an image (1200x630px recommended) and upload it to your server.

### 4. **Add Favicon**
Create a favicon and place it in `/static/favicon.ico` or update line 14:
```html
<link rel="icon" type="image/x-icon" href="/path/to/your/favicon.ico">
```

### 5. **Set Up Form Handling**
The forms currently show success messages locally. To handle real submissions:

**Option A: Use a form service** (easiest)
- Formspree: https://formspree.io/
- Basin: https://usebasin.com/
- Web3Forms: https://web3forms.com/

**Option B: Create backend endpoint**
Update the form submission code (around line 680-690 and 730-740) to send to your API:
```javascript
fetch('/api/contact', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
})
```

### 6. **Enable HTTPS**
- Get an SSL certificate (free from Let's Encrypt or your hosting provider)
- Uncomment lines in `.htaccess` to force HTTPS:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 7. **Replace Sample Images**
The website uses placeholder images from Unsplash. Consider replacing with your own:
- Hero background (line 26)
- About section image (line 525)
- Gallery images (lines 575-577)

### 8. **Update Domain URLs**
Search for `wiyulemotors.com` and replace with your actual domain in:
- Meta tags
- Canonical URL
- Structured data
- Sitemap

## 🌐 Deployment Instructions

### Option 1: Traditional Web Hosting (Recommended for beginners)

**Popular Hosting Providers:**
- HostGator
- Bluehost
- SiteGround
- DreamHost
- A2 Hosting

**Steps:**
1. Purchase hosting plan with domain
2. Access cPanel or hosting control panel
3. Open File Manager
4. Navigate to `public_html` or `www` folder
5. Upload all files
6. Set correct permissions (644 for files, 755 for folders)
7. Visit your domain

### Option 2: Free Hosting (For testing)

**Netlify (Recommended):**
1. Create account at https://netlify.com
2. Drag and drop your files
3. Get free subdomain (yoursite.netlify.app)
4. Optional: Connect custom domain

**GitHub Pages:**
1. Create GitHub account
2. Create new repository
3. Upload files
4. Enable GitHub Pages in settings
5. Access at username.github.io/repository-name

**Vercel:**
1. Sign up at https://vercel.com
2. Import project
3. Deploy with one click

### Option 3: VPS/Cloud Server

For more control (requires technical knowledge):
- DigitalOcean
- AWS (Amazon Web Services)
- Google Cloud Platform
- Linode

## 📱 Testing Before Launch

### Essential Tests:

1. **Mobile Responsiveness**
   - Open in Chrome DevTools
   - Test on actual mobile devices
   - Check all breakpoints

2. **Cross-Browser Testing**
   - Chrome
   - Firefox
   - Safari
   - Edge

3. **Forms**
   - Submit contact form
   - Submit booking form
   - Verify validation works
   - Check success messages

4. **Navigation**
   - Test all menu links
   - Test mobile menu
   - Verify smooth scrolling

5. **Performance**
   - Run Google PageSpeed Insights
   - Test loading speed
   - Check image optimization

6. **SEO**
   - Verify meta tags
   - Check robots.txt accessibility
   - Validate sitemap.xml
   - Test structured data at https://search.google.com/test/rich-results

## 🔄 Regular Maintenance

- Update testimonials regularly
- Refresh gallery images
- Keep business hours current
- Update services as needed
- Monitor Google Analytics
- Check and fix broken links
- Keep phone numbers current

## 📊 Analytics & Tracking

After setup, monitor:
- Page views
- Bounce rate
- Booking form submissions
- Contact form submissions
- Popular services
- Mobile vs desktop traffic

## 🛠️ Future Enhancements (Optional)

Consider adding:
- Blog section for auto maintenance tips
- Customer login portal
- Online payment system
- Service history tracking
- Appointment calendar
- Live chat widget
- Email newsletter signup
- Customer reviews system
- Social media feed integration

## 📞 Support

For technical questions about the website code:
- Review the comments in index.html
- Check the FAQ section
- Test locally in your browser

For hosting and deployment help:
- Contact your hosting provider
- Check hosting documentation
- Community forums (Stack Overflow, Reddit)

## 📄 License

This website is proprietary and created specifically for Wiyule Motors. All rights reserved.

---

**Ready to Launch?** ✨

Once you've completed the checklist above, your website is ready to go live!

Good luck with your launch! 🚀
