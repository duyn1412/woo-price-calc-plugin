(function(apiFetch){
    // Modules Query Filters loaded
    
    const REST = {
      path: '/wp-json/wp/v2/modules',
      nonce: window.wp?.apiFetch?.nonce || ''
    };
  
    function q(selector, ctx=document){ return ctx.querySelector(selector); }
    function qa(selector, ctx=document){ return Array.from(ctx.querySelectorAll(selector)); }
    
    // Check if device is mobile
    function isMobile() {
      return window.innerWidth <= 768;
    }
    
    // Get responsive per page count
    function getPerPage() {
      return isMobile() ? 3 : 8;
    }
    
    // Show loading state
    function showLoading() {
      const container = document.querySelector('.modules-results-container');
      if (container) {
        container.classList.add('loading');
        const loadingEl = container.querySelector('.loading-container');
        if (loadingEl) {
          loadingEl.style.display = 'flex';
        }
      }
    }
    
    // Hide loading state
    function hideLoading() {
      const container = document.querySelector('.modules-results-container');
      if (container) {
        container.classList.remove('loading');
        const loadingEl = container.querySelector('.loading-container');
        if (loadingEl) {
          loadingEl.style.display = 'none';
        }
      }
    }
  
    // Find the target element by anchor (id="<anchor>")
    function getTargetEl(anchor){ 
      return document.getElementById(anchor);
    }
    
    // Create target element if it doesn't exist
    function createTargetElement(anchor){
      const targetEl = document.createElement('div');
      targetEl.id = anchor;
      
      const existingContainer = document.querySelector('.modules-results-container');
      
      if (existingContainer) {
        const existingTarget = existingContainer.querySelector('#' + anchor);
        if (existingTarget) {
          return existingTarget;
        }
        existingContainer.appendChild(targetEl);
      } else {
        const form = document.querySelector('form.modules-filters');
        if (form) {
          form.parentNode.insertBefore(targetEl, form.nextSibling);
        } else {
          document.body.appendChild(targetEl);
        }
      }
      
      return targetEl;
    }
  
    function buildDefaults(form){
      return {
        anchor: form.dataset.anchor,
        query: {
          postType: 'modules', // Use modules post type
          perPage: getPerPage(), // Responsive: 3 on mobile, 8 on desktop
          orderBy: (form.dataset.orderby || 'date'),
          order: (form.dataset.order || 'desc').toLowerCase(), // Keep lowercase
          page: 1,
          inherit: false
        }
      };
    }
  
    function buildAttributes(form){
      const fd = new FormData(form);
      const attrs = buildDefaults(form);
  
      // search
      const search = fd.get('s');
      if (search) {
        attrs.query.search = search;
      }
      
      // orderby
      const orderby = fd.get('orderby');
      if (orderby) {
        attrs.query.orderBy = orderby;
      }
      
      // order
      const order = fd.get('order');
      if (order) {
        attrs.query.order = order.toUpperCase();
      }

      // taxonomy
      const taxonomy = form.dataset.taxonomy || 'industry';
      const terms = getSelectedTerms(form);
      
      if (terms.length){
        attrs.query.taxQuery = [{
          taxonomy,
          terms,
          field: 'term_id',
          operator: 'IN',
          includeChildren: true
        }];
      }

      return attrs;
    }
  
    async function renderQuery(attributes){
      try {
        const query = attributes.query;
        const postType = query.postType || 'modules';
        const currentPage = query.page || 1;
        const perPage = getPerPage();
        
        // Build API URL with parameters
        let url = `/wp-json/wp/v2/${postType}?per_page=${perPage}&page=${currentPage}&orderby=${query.orderBy || 'date'}&_embed=author,wp:featuredmedia,wp:term`;
        
        // Fix order parameter - ensure lowercase
        const order = (query.order || 'desc').toLowerCase();
        url += `&order=${order}`;
        
        // Add taxonomy filter if exists
        if (query.taxQuery && query.taxQuery.length > 0) {
          const taxQuery = query.taxQuery[0];
          url += `&${taxQuery.taxonomy}=${taxQuery.terms.join(',')}`;
        }
        
        // Add search if exists
        if (query.search) {
          url += `&search=${encodeURIComponent(query.search)}`;
        }
        
        // Get posts from API with headers
        const response = await fetch(url, {
          method: 'GET'
          // Temporarily remove nonce to test
        });
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const res = await response.json();
        
        // Get pagination headers from the same response
        const xWpTotal = response.headers.get('X-WP-Total');
        const xWpTotalPages = response.headers.get('X-WP-TotalPages');
        
        let totalPages = null;
        let totalPosts = null;
        
        if (xWpTotal) totalPosts = parseInt(xWpTotal);
        if (xWpTotalPages) totalPages = parseInt(xWpTotalPages);
        
        // Build simple results HTML
        return await buildSimpleResults(res, attributes, currentPage, perPage, totalPages, totalPosts);
        
      } catch (error) {
        console.error('❌ Query rendering error:', error);
        return '';
      }
    }
  
    async function buildSimpleResults(posts, attributes, currentPage, perPage, totalPagesFromAPI = null, totalPostsFromAPI = null){
      if (!posts || !Array.isArray(posts)) {
        console.log('📄 No posts to render');
        return '';
      }
      
      console.log('📄 Building simple results with', posts.length, 'posts, page', currentPage);
      
      // Debug: Log first post structure
      if (posts.length > 0) {
        console.log('📋 First post structure:', posts[0]);
        console.log('📋 Post title:', posts[0].title);
        console.log('📋 Post excerpt:', posts[0].excerpt);
        console.log('📋 Post link:', posts[0].link);
        console.log('📋 Post date:', posts[0].date);
      }
      
      // Use API headers if available, otherwise estimate
      let totalPages = totalPagesFromAPI;
      let totalPosts = totalPostsFromAPI;
      
      if (!totalPages) {
        // Fallback: estimate based on current page and posts length
        if (posts.length === perPage) {
          // If we got exactly perPage items, assume there are more pages
          totalPages = currentPage + 1; // At least one more page
        } else if (posts.length < perPage) {
          // If we got fewer than perPage items, this is the last page
          totalPages = currentPage;
        } else {
          totalPages = currentPage;
        }
      }
      
      if (!totalPosts) {
        totalPosts = posts.length;
      }
      
      console.log('📄 Pagination calculation:', {
        postsLength: posts.length,
        perPage: perPage,
        currentPage: currentPage,
        totalPages: totalPages,
        totalPosts: totalPosts,
        fromAPI: { totalPagesFromAPI, totalPostsFromAPI }
      });
      
      let html = `
        <div class="results-list">
      `;
      
      // Render posts
      posts.forEach((post, index) => {
        // Safe access to post properties
        const title = post.title?.rendered || post.title || 'No Title';
        let excerpt = post.excerpt?.rendered || post.excerpt || 'No excerpt available';
        
        // Clean excerpt: remove HTML tags and "read more" text
        excerpt = excerpt.replace(/<[^>]*>/g, ''); // Remove HTML tags
        excerpt = excerpt.replace(/read more/gi, ''); // Remove "read more" text
        excerpt = excerpt.replace(/\[&hellip;\]/g, ''); // Remove WordPress ellipsis
        excerpt = excerpt.trim();
        
        // Limit to 8 words
        const words = excerpt.split(' ');
        if (words.length > 8) {
          excerpt = words.slice(0, 8).join(' ') + '...';
        }
        
        const link = post.link || '#';
        const date = post.date ? new Date(post.date).toLocaleDateString() : 'No date';
        
        // Debug: Log post structure to understand data
        console.log('🔍 Post data for', title, ':', {
          featured_media: post.featured_media,
          embedded: post._embedded,
          author: post.author,
          meta: post.meta
        });
        
        // Get featured image
        let imageUrl = 'module-preview.png'; // Default fallback
        
        if (post.featured_media && post._embedded && post._embedded['wp:featuredmedia']) {
          const featuredMedia = post._embedded['wp:featuredmedia'][0];
          if (featuredMedia && featuredMedia.source_url) {
            imageUrl = featuredMedia.source_url;
          }
        }
        
        const featuredImage = `<img src="${imageUrl}" alt="Module Preview" class="module-img" />`;
        
        // Get author name
        let authorName = 'Unknown Developer';
        
        if (post._embedded && post._embedded['author'] && post._embedded['author'][0]) {
          authorName = post._embedded['author'][0].name;
        } else if (post.author) {
          // Fallback to author ID if embedded data not available
          authorName = `Author ID: ${post.author}`;
        }
        
        console.log('👤 Author for', title, ':', authorName);
        
        // Get brand from taxonomy
        let brandName = 'Unknown Brand';
        if (post._embedded && post._embedded['wp:term']) {
          // Find brand taxonomy terms
          const brandTerms = post._embedded['wp:term'].find(terms => 
            terms.some(term => term.taxonomy === 'brand')
          );
          if (brandTerms) {
            const brandTerm = brandTerms.find(term => term.taxonomy === 'brand');
            if (brandTerm) {
              brandName = brandTerm.name;
            }
          }
        }
        
        console.log('🏷️ Brand for post:', title, '=', brandName);
        
        // Get rating from API response
        const rating = post.acf_rating || 'No rating';
        
        html += `
          <div class="result-item">
            <a href="${link}" class="module-info">
              ${featuredImage}
              <h3>${title}</h3>
              <p>${excerpt}</p>
              <div class="rating">${rating}</div>
              <div class="meta">
                <div><strong>Brand:</strong> <span>${brandName}</span></div>
                <div><strong>Developer:</strong> <span>${authorName}</span></div>
              </div>
              <span class="btn-primary btn-view-module">View Module</span>
            </a>
          </div>
        `;
      });
      
      
      html += `
          </div>
          
          <div class="pagination">
            <div class="page-info">Page ${currentPage} of ${totalPages}</div>
      `;
      
      // Build pagination with smart page display
      const maxVisiblePages = 5;
      let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
      let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
      
      // Adjust start page if we're near the end
      if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
      }
      
      console.log('📄 Pagination range:', { startPage, endPage, currentPage, totalPages });
      
      // Add Previous button
      if (currentPage > 1) {
        html += `<a class="page-numbers prev" href="#" data-page="${currentPage - 1}">« Previous</a>`;
      }
      
      // Add first page if not in range
      if (startPage > 1) {
        html += `<a class="page-numbers" href="#" data-page="1">1</a>`;
        if (startPage > 2) {
          html += `<span class="page-numbers dots">...</span>`;
        }
      }
      
      // Add page numbers in range
      for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
          html += `<span class="page-numbers current">${i}</span>`;
        } else {
          html += `<a class="page-numbers" href="#" data-page="${i}">${i}</a>`;
        }
      }
      
      // Add last page if not in range
      if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
          html += `<span class="page-numbers dots">...</span>`;
        }
        html += `<a class="page-numbers" href="#" data-page="${totalPages}">${totalPages}</a>`;
      }
      
      // Add Next button
      if (currentPage < totalPages) {
        html += `<a class="page-numbers next" href="#" data-page="${currentPage + 1}">Next »</a>`;
      }
      
      html += `
          </div>
        </div>
      `;
      
      console.log('📄 Built simple results HTML');
      return html;
    }
    
    function replaceResultsHtml(targetEl, html){
      if (targetEl) {
        console.log('🔄 Replacing target element content with results');
        
        // Hide loading state
        hideLoading();
        
        // Replace entire content with new results
        targetEl.innerHTML = html;
        
        // Add event listeners for pagination
        addPaginationListeners(targetEl);
        
        // Re-initialize accordion functionality for any new content
        initAccordion();
        
        // Load author names after content is rendered
        loadAuthorNames();
        
        console.log('✅ Results replaced successfully');
      } else {
        console.error('❌ Target element not found for replacement');
      }
    }


    // Load author names for posts that show "Author ID: X"
    async function loadAuthorNames() {
      console.log('👤 Loading author names...');
      const authorElements = document.querySelectorAll('.meta p:last-child');
      
      for (const element of authorElements) {
        const text = element.textContent;
        if (text.includes('Author ID:')) {
          const authorId = text.replace('Author ID:', '').trim();
          try {
            const response = await fetch(`/wp-json/wp/v2/users/${authorId}`);
            if (response.ok) {
              const authorData = await response.json();
              element.innerHTML = `<strong>Developer:</strong> ${authorData.name}`;
            }
          } catch (error) {
            console.error('❌ Error loading author', authorId, ':', error);
          }
        }
      }
      
      console.log('✅ Author names loaded');
    }

    function addPaginationListeners(targetEl){
      console.log('🔗 Adding pagination event listeners');
      
      // Add click listeners to pagination links
      const paginationLinks = targetEl.querySelectorAll('.page-numbers[data-page]');
      paginationLinks.forEach(link => {
        link.addEventListener('click', async function(e){
          e.preventDefault();
          
          const page = parseInt(this.getAttribute('data-page'));
          console.log('📄 Pagination clicked, page:', page);
          
          // Show loading state
          showLoading();
          
          // Get current form data
          const form = document.querySelector('form.modules-filters');
          if (form) {
            // Build attributes with new page
            const attrs = buildAttributes(form);
            attrs.query.page = page;
            
            console.log('🌐 Rendering query for page:', page);
            const html = await renderQuery(attrs);
            
            // Replace content
            replaceResultsHtml(targetEl, html);
          }
        });
      });
      
      console.log('✅ Added', paginationLinks.length, 'pagination listeners');
    }

    async function loadDefaultList(form, anchor){
      console.log('📄 Loading default list for anchor:', anchor);
      
      // Show loading state
      showLoading();
      
      try {
        // Build default attributes (no filters)
        const attrs = buildDefaults(form);
        console.log('📊 Default attributes:', attrs);
        
        // Render default query
        console.log('🌐 Rendering default query...');
        const html = await renderQuery(attrs);
        console.log('📄 Default query rendered, HTML length:', html.length);
        
        // Create or find target element
        let targetEl = getTargetEl(anchor);
        if (!targetEl) {
          console.log('🔨 Creating new target element with ID:', anchor);
          targetEl = createTargetElement(anchor);
        }
        
        if (targetEl) {
          console.log('✅ Target element ready, loading default content');
          replaceResultsHtml(targetEl, html);
        } else {
          console.error('❌ Could not create or find target element');
        }
        
      } catch (error) {
        console.error('❌ Error loading default list:', error);
      }
    }

    function getSelectedTerms(form){
      const terms = [];
      const checkboxes = form.querySelectorAll('input[type="checkbox"][name="tax_terms[]"]:checked');
      checkboxes.forEach(cb => {
        if (cb.value) {
          terms.push(parseInt(cb.value));
        }
      });
      console.log('🏷️ Selected terms:', terms);
      return terms;
    }
  
    // Initialize accordion functionality for parent terms only
    function initAccordion() {
      console.log('🎛️ Initializing parent term accordion functionality');
      
      // Use event delegation on the form
      const form = document.querySelector('form.modules-filters');
      if (!form) {
        console.error('❌ No form found for event delegation');
        return;
      }
      
      // Remove existing event listener
      form.removeEventListener('click', handleParentClick);
      
      // Add event delegation
      form.addEventListener('click', handleParentClick);
      
      // Initialize icons for existing parents
      const taxParents = document.querySelectorAll('.tax-item-parent');
      console.log('🎛️ Found', taxParents.length, 'parent terms for accordion');
      
      taxParents.forEach((parent, index) => {
        console.log('🎛️ Processing parent', index + 1, ':', parent);
        
        // Add toggle icon if not exists
        let toggle = parent.querySelector('.parent-toggle');
        if (!toggle) {
          toggle = document.createElement('div');
          toggle.className = 'parent-toggle';
          parent.appendChild(toggle);
        }
        
        // Set initial icon based on collapsed state
        updateToggleIcon(parent, toggle);
      });
      
      console.log('✅ Parent term accordion functionality initialized for', taxParents.length, 'parents');
    }
    
    // Handle parent click with event delegation
    function handleParentClick(e) {
      // Only handle clicks on parent text or toggle icon, not on checkboxes
      const parent = e.target.closest('.tax-item-parent');
      if (!parent) return;
      
      // Don't handle clicks on checkboxes or labels
      if (e.target.type === 'checkbox' || e.target.tagName === 'LABEL' || e.target.closest('label')) {
        return;
      }
      
          e.preventDefault();
      console.log('🎛️ Parent clicked, toggling accordion');
      console.log('🎛️ Before toggle - classes:', parent.className);
      
      // Toggle collapsed class
      parent.classList.toggle('collapsed');
      
      const isCollapsed = parent.classList.contains('collapsed');
      console.log('🎛️ After toggle - classes:', parent.className);
      console.log('🎛️ Accordion state:', isCollapsed ? 'collapsed' : 'expanded');
      
      // Update icon
      const toggle = parent.querySelector('.parent-toggle');
      if (toggle) {
        updateToggleIcon(parent, toggle);
      }
    }
    
    // Update toggle icon based on state
    function updateToggleIcon(parent, toggle) {
      const isCollapsed = parent.classList.contains('collapsed');
      toggle.innerHTML = isCollapsed ? `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M6 12H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M12 18V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      ` : `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
          <path d="M6 12H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      `;
    }


    // Initialize auto-submit on checkbox change
    function initAutoSubmit(form, anchor) {
      console.log('🔄 Initializing auto-submit functionality');
      
      // Add change listeners to all checkboxes
      const checkboxes = form.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', async function() {
          console.log('☑️ Checkbox changed, auto-submitting form');
          
          // Show loading state
          showLoading();
          
          // Build attributes from form
          const attrs = buildAttributes(form);
          console.log('📊 Built attributes:', attrs);
          
          // Render query
          console.log('🌐 Rendering query...');
          const html = await renderQuery(attrs);
          console.log('📄 Query rendered, HTML length:', html.length);
          
          // Find or create target element
          let targetEl = getTargetEl(anchor);
          if (!targetEl) {
            console.log('🔨 Creating new target element with ID:', anchor);
            targetEl = createTargetElement(anchor);
          }
          
          // Replace content
          if (targetEl) {
            replaceResultsHtml(targetEl, html);
          } else {
            console.error('❌ Could not find or create target element');
          }
        });
      });
      
      console.log('✅ Auto-submit functionality initialized for', checkboxes.length, 'checkboxes');
    }

    // Main initialization
    document.addEventListener('DOMContentLoaded', function(){
      console.log('🚀 DOM Content Loaded - Initializing Modules Filters');
      
      const form = q('form.modules-filters');
      if (!form) {
        console.error('❌ No modules filter form found');
        return;
      }
      
      const anchor = form.dataset.anchor || 'modules-results';
      console.log('🎯 Target anchor:', anchor);
      
      // Debug: Check initial HTML structure
      const container = document.querySelector('.modules-results-container');
      if (container) {
        console.log('📋 Initial container HTML:', container.outerHTML);
        const heading = container.querySelector('h3');
        const description = container.querySelector('.description');
        console.log('📋 Initial - Heading exists:', !!heading, 'Description exists:', !!description);
        if (heading) console.log('📋 Heading text:', heading.textContent);
        if (description) console.log('📋 Description text:', description.textContent);
      } else {
        console.log('❌ No .modules-results-container found');
      }
      
      // Initialize accordion functionality
      initAccordion();
      
      // Add auto-submit on checkbox change
      initAutoSubmit(form, anchor);
      
      // Load default list on page load
      loadDefaultList(form, anchor);
      
      // Form submit handler
      form.addEventListener('submit', async function(e){
        e.preventDefault();
        console.log('📝 Form submitted');
        
        // Build attributes from form
        const attrs = buildAttributes(form);
        console.log('📊 Built attributes:', attrs);
        
        // Show loading state
        showLoading();
        
        // Render query
        console.log('🌐 Rendering query...');
        const html = await renderQuery(attrs);
        console.log('📄 Query rendered, HTML length:', html.length);
        
        // Find or create target element
        let targetEl = getTargetEl(anchor);
        if (!targetEl) {
          console.log('🔨 Creating new target element with ID:', anchor);
          targetEl = createTargetElement(anchor);
        }
        
        // Replace content
        if (targetEl) {
          replaceResultsHtml(targetEl, html);
        } else {
          console.error('❌ Could not find or create target element');
        }
      });
      
      // Clear button handler
      const clearBtn = form.querySelector('button.clear-button');
      if (clearBtn) {
        clearBtn.addEventListener('click', async function(e){
          e.preventDefault();
          console.log('🔄 Clear button clicked');
          
          // Uncheck all checkboxes
          const checkboxes = form.querySelectorAll('input[type="checkbox"]');
          checkboxes.forEach(checkbox => {
            checkbox.checked = false;
          });
          
          // Clear search input
          const searchInput = form.querySelector('input[type="text"]');
          if (searchInput) {
            searchInput.value = '';
          }
          
          // Show loading state
          showLoading();
          
          // Load default list
          loadDefaultList(form, anchor);
        });
      }
      
      console.log('✅ Modules Filters initialized successfully');
    });
    
    // Handle window resize for responsive perPage
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function() {
        console.log('📱 Window resized, checking if perPage should change');
        
        const form = document.querySelector('form.modules-filters');
        if (form) {
          const anchor = form.dataset.anchor || 'modules-results';
          const targetEl = document.getElementById(anchor);
          
          // Only reload if there's content and the perPage would change
          if (targetEl && targetEl.innerHTML.trim() && !targetEl.querySelector('.loading-container')) {
            console.log('🔄 Reloading with new perPage:', getPerPage());
            loadDefaultList(form, anchor);
          }
        }
      }, 300); // Debounce resize events
    });
  
  })(window.wp?.apiFetch || window.apiFetch);