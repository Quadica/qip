import React, { useState } from 'react';
import { ChevronLeft, Search, Calendar, Package, Hash, Clock, CheckCircle, Loader2, ChevronRight, RotateCcw, Filter, X } from 'lucide-react';

/*
 * Luxeon Star LEDs / Quadica LEDs Brand Guidelines - DARK THEME
 * Batch History Screen - View previously completed engraving batches for re-engraving
 */

// Brand color constants for dark theme
const colors = {
  // Backgrounds
  bgPrimary: '#0a1628',
  bgCard: '#0f1f35',
  bgElevated: '#152a45',
  bgHover: '#1a3352',
  
  // Brand blues
  deepNavy: '#01236d',
  luxeonBlue: '#0056A4',
  electricBlue: '#00A0E3',
  skyBlue: '#109cf6',
  coolLed: '#87CEEB',
  
  // Text
  textPrimary: '#FFFFFF',
  textSecondary: '#A8B4BD',
  textMuted: '#6b7c8f',
  
  // Accents
  warmLed: '#FFB347',
  success: '#28A745',
  alert: '#DC3545',
  
  // Borders
  border: '#1e3a5f',
  borderLight: '#2a4a6f',
};

// Mock data for batch history
const mockBatchHistory = [
  {
    id: 1042,
    createdAt: '2025-12-29T14:32:00',
    status: 'completed',
    completedAt: '2025-12-29T16:45:00',
    totalModules: 48,
    totalArrays: 6,
    moduleTypes: ['CORE', 'SOLO'],
    orderIds: ['284534', '284521', '284498'],
    modules: [
      { sku: 'CORE-91247', qty: 16, serialStart: '00001001', serialEnd: '00001016', orderId: '284534' },
      { sku: 'CORE-38455', qty: 8, serialStart: '00001017', serialEnd: '00001024', orderId: '284521' },
      { sku: 'SOLO-34543', qty: 24, serialStart: '00001025', serialEnd: '00001048', orderId: '284498' }
    ]
  },
  {
    id: 1041,
    createdAt: '2025-12-28T09:15:00',
    status: 'completed',
    completedAt: '2025-12-28T11:30:00',
    totalModules: 32,
    totalArrays: 4,
    moduleTypes: ['EDGE'],
    orderIds: ['284467', '284455'],
    modules: [
      { sku: 'EDGE-58324', qty: 16, serialStart: '00000969', serialEnd: '00000984', orderId: '284467' },
      { sku: 'EDGE-19847', qty: 16, serialStart: '00000985', serialEnd: '00001000', orderId: '284455' }
    ]
  },
  {
    id: 1040,
    createdAt: '2025-12-27T13:45:00',
    status: 'completed',
    completedAt: '2025-12-27T15:20:00',
    totalModules: 64,
    totalArrays: 8,
    moduleTypes: ['STAR'],
    orderIds: ['284401'],
    modules: [
      { sku: 'STAR-84219', qty: 32, serialStart: '00000905', serialEnd: '00000936', orderId: '284401' },
      { sku: 'STAR-72156', qty: 32, serialStart: '00000937', serialEnd: '00000968', orderId: '284401' }
    ]
  },
  {
    id: 1039,
    createdAt: '2025-12-26T10:00:00',
    status: 'completed',
    completedAt: '2025-12-26T12:15:00',
    totalModules: 24,
    totalArrays: 3,
    moduleTypes: ['CORE', 'EDGE'],
    orderIds: ['284389', '284372'],
    modules: [
      { sku: 'CORE-23405', qty: 8, serialStart: '00000881', serialEnd: '00000888', orderId: '284389' },
      { sku: 'CORE-45946', qty: 8, serialStart: '00000889', serialEnd: '00000896', orderId: '284389' },
      { sku: 'EDGE-33291', qty: 8, serialStart: '00000897', serialEnd: '00000904', orderId: '284372' }
    ]
  },
  {
    id: 1038,
    createdAt: '2025-12-24T11:30:00',
    status: 'completed',
    completedAt: '2025-12-24T14:00:00',
    totalModules: 40,
    totalArrays: 5,
    moduleTypes: ['SOLO', 'STAR'],
    orderIds: ['284345', '284332', '284318'],
    modules: [
      { sku: 'SOLO-78291', qty: 16, serialStart: '00000841', serialEnd: '00000856', orderId: '284345' },
      { sku: 'SOLO-45182', qty: 8, serialStart: '00000857', serialEnd: '00000864', orderId: '284332' },
      { sku: 'STAR-29384', qty: 16, serialStart: '00000865', serialEnd: '00000880', orderId: '284318' }
    ]
  }
];

export default function BatchHistory() {
  const [batches] = useState(mockBatchHistory);
  const [selectedBatchId, setSelectedBatchId] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterType, setFilterType] = useState('all'); // all, CORE, SOLO, EDGE, STAR
  const [showFilters, setShowFilters] = useState(false);

  // Format date for display
  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // Format relative time
  const formatRelativeTime = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    return `${Math.floor(diffDays / 30)} months ago`;
  };

  // Filter batches based on search and type filter
  const filteredBatches = batches.filter(batch => {
    const matchesSearch = searchQuery === '' || 
      batch.id.toString().includes(searchQuery) ||
      batch.orderIds.some(id => id.includes(searchQuery)) ||
      batch.modules.some(m => m.sku.toLowerCase().includes(searchQuery.toLowerCase()));
    
    const matchesType = filterType === 'all' || 
      batch.moduleTypes.includes(filterType);
    
    return matchesSearch && matchesType;
  });

  // Get selected batch details
  const selectedBatch = batches.find(b => b.id === selectedBatchId);

  // Load batch into Batch Creator
  const loadBatch = () => {
    if (selectedBatch) {
      // TODO: Navigate to Batch Creator with selected batch data
      console.log('Load batch into Batch Creator:', selectedBatch);
      alert(`Loading Batch #${selectedBatch.id} into Module Engraving Batch Creator...`);
    }
  };

  return (
    <div 
      className="min-h-screen p-6"
      style={{ 
        fontFamily: "'Roboto', 'Segoe UI', 'Arial', sans-serif",
        backgroundColor: colors.bgPrimary,
        color: colors.textPrimary
      }}
    >
      <div className="max-w-6xl mx-auto">
        {/* Brand Header Bar */}
        <div 
          className="rounded-t-lg px-6 py-4 flex items-center justify-between"
          style={{ 
            background: `linear-gradient(135deg, ${colors.deepNavy} 0%, ${colors.luxeonBlue} 100%)`,
            borderBottom: `1px solid ${colors.electricBlue}`
          }}
        >
          <div className="flex items-center gap-3">
            <button
              onClick={() => {
                // TODO: Navigate back to Batch Creator
                console.log('Navigate back to Batch Creator');
              }}
              className="p-2 rounded transition-all"
              style={{ color: colors.coolLed }}
              onMouseOver={(e) => {
                e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.1)';
              }}
              onMouseOut={(e) => {
                e.currentTarget.style.backgroundColor = 'transparent';
              }}
              title="Back to Batch Creator"
            >
              <ChevronLeft className="w-6 h-6" />
            </button>
            <div 
              className="w-10 h-10 rounded-lg flex items-center justify-center"
              style={{ 
                backgroundColor: colors.electricBlue,
                boxShadow: `0 0 20px ${colors.electricBlue}40`
              }}
            >
              <Clock className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-white tracking-tight">
                Engraving Batch History
              </h1>
              <p className="text-sm" style={{ color: colors.skyBlue }}>
                LUXEON STAR LEDs Production System
              </p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-xs" style={{ color: colors.coolLed }}>Quadica Developments Inc.</p>
          </div>
        </div>

        {/* Search and Filter Bar */}
        <div 
          className="px-4 py-3 flex items-center gap-4"
          style={{ 
            backgroundColor: colors.bgCard,
            borderLeft: `1px solid ${colors.border}`,
            borderRight: `1px solid ${colors.border}`
          }}
        >
          {/* Search Input */}
          <div className="flex-1 relative">
            <Search 
              className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" 
              style={{ color: colors.textMuted }} 
            />
            <input
              type="text"
              placeholder="Search by batch ID, order ID, or module SKU..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-10 pr-4 py-2 rounded text-sm"
              style={{
                backgroundColor: colors.bgElevated,
                color: colors.textPrimary,
                border: `1px solid ${colors.border}`,
                outline: 'none'
              }}
              onFocus={(e) => {
                e.target.style.borderColor = colors.electricBlue;
                e.target.style.boxShadow = `0 0 0 2px ${colors.electricBlue}30`;
              }}
              onBlur={(e) => {
                e.target.style.borderColor = colors.border;
                e.target.style.boxShadow = 'none';
              }}
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-3 top-1/2 transform -translate-y-1/2"
                style={{ color: colors.textMuted }}
              >
                <X className="w-4 h-4" />
              </button>
            )}
          </div>

          {/* Filter Toggle */}
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="px-3 py-2 rounded flex items-center gap-2 text-sm transition-all"
            style={{ 
              backgroundColor: showFilters ? `${colors.electricBlue}20` : colors.bgElevated,
              color: showFilters ? colors.electricBlue : colors.textSecondary,
              border: `1px solid ${showFilters ? colors.electricBlue : colors.border}`
            }}
          >
            <Filter className="w-4 h-4" />
            Filters
          </button>
        </div>

        {/* Filter Options */}
        {showFilters && (
          <div 
            className="px-4 py-3 flex items-center gap-2"
            style={{ 
              backgroundColor: colors.bgElevated,
              borderLeft: `1px solid ${colors.border}`,
              borderRight: `1px solid ${colors.border}`,
              borderBottom: `1px solid ${colors.border}`
            }}
          >
            <span className="text-xs" style={{ color: colors.textMuted }}>Module Type:</span>
            {['all', 'CORE', 'SOLO', 'EDGE', 'STAR'].map(type => (
              <button
                key={type}
                onClick={() => setFilterType(type)}
                className="px-3 py-1 rounded text-xs font-medium transition-all"
                style={{ 
                  backgroundColor: filterType === type ? colors.electricBlue : colors.bgCard,
                  color: filterType === type ? '#FFFFFF' : colors.textSecondary,
                  border: `1px solid ${filterType === type ? colors.electricBlue : colors.border}`
                }}
              >
                {type === 'all' ? 'All Types' : type}
              </button>
            ))}
          </div>
        )}

        {/* Main Content Area */}
        <div 
          className="flex"
          style={{ 
            borderLeft: `1px solid ${colors.border}`,
            borderRight: `1px solid ${colors.border}`,
            borderBottom: `1px solid ${colors.border}`,
            borderRadius: '0 0 8px 8px',
            overflow: 'hidden'
          }}
        >
          {/* Batch List */}
          <div 
            className="w-1/2"
            style={{ 
              backgroundColor: colors.bgCard,
              borderRight: `1px solid ${colors.border}`
            }}
          >
            {/* List Header */}
            <div 
              className="px-4 py-2 flex items-center gap-2"
              style={{ 
                backgroundColor: colors.bgElevated,
                borderBottom: `1px solid ${colors.border}`
              }}
            >
              <Package className="w-4 h-4" style={{ color: colors.electricBlue }} />
              <span 
                className="text-xs uppercase tracking-wider font-semibold"
                style={{ color: colors.electricBlue }}
              >
                Completed Batches ({filteredBatches.length})
              </span>
            </div>

            {/* Batch Items */}
            <div style={{ maxHeight: '500px', overflowY: 'auto' }}>
              {filteredBatches.length === 0 ? (
                <div 
                  className="p-8 text-center"
                  style={{ color: colors.textMuted }}
                >
                  <Package className="w-12 h-12 mx-auto mb-3 opacity-50" />
                  <p>No batches found matching your criteria</p>
                </div>
              ) : (
                filteredBatches.map((batch, index) => {
                  const isSelected = selectedBatchId === batch.id;
                  const isLast = index === filteredBatches.length - 1;
                  
                  return (
                    <div 
                      key={batch.id}
                      onClick={() => setSelectedBatchId(batch.id)}
                      className="p-4 cursor-pointer transition-all"
                      style={{ 
                        backgroundColor: isSelected ? `${colors.electricBlue}15` : 'transparent',
                        borderBottom: isLast ? 'none' : `1px solid ${colors.border}`,
                        borderLeft: isSelected ? `3px solid ${colors.electricBlue}` : '3px solid transparent'
                      }}
                      onMouseOver={(e) => {
                        if (!isSelected) {
                          e.currentTarget.style.backgroundColor = colors.bgHover;
                        }
                      }}
                      onMouseOut={(e) => {
                        if (!isSelected) {
                          e.currentTarget.style.backgroundColor = 'transparent';
                        }
                      }}
                    >
                      {/* Batch Header */}
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                          <span 
                            className="text-lg font-bold"
                            style={{ color: isSelected ? colors.electricBlue : colors.textPrimary }}
                          >
                            Batch #{batch.id}
                          </span>
                          <span 
                            className="px-2 py-0.5 rounded text-xs flex items-center gap-1"
                            style={{ 
                              backgroundColor: `${colors.success}20`,
                              color: colors.success
                            }}
                          >
                            <CheckCircle className="w-3 h-3" />
                            Completed
                          </span>
                        </div>
                        <span 
                          className="text-xs"
                          style={{ color: colors.textMuted }}
                        >
                          {formatRelativeTime(batch.completedAt)}
                        </span>
                      </div>

                      {/* Batch Stats */}
                      <div className="flex items-center gap-4 text-sm mb-2">
                        <div className="flex items-center gap-1">
                          <Package className="w-3 h-3" style={{ color: colors.textMuted }} />
                          <span style={{ color: colors.textSecondary }}>{batch.totalModules} modules</span>
                        </div>
                        <div className="flex items-center gap-1">
                          <Hash className="w-3 h-3" style={{ color: colors.textMuted }} />
                          <span style={{ color: colors.textSecondary }}>{batch.totalArrays} arrays</span>
                        </div>
                        <div className="flex items-center gap-1">
                          <span style={{ color: colors.textMuted }}>Orders:</span>
                          <span style={{ color: colors.textSecondary }}>
                            {batch.orderIds.length > 2 
                              ? `${batch.orderIds.slice(0, 2).join(', ')} +${batch.orderIds.length - 2}`
                              : batch.orderIds.join(', ')}
                          </span>
                        </div>
                      </div>

                      {/* Module Type Tags */}
                      <div className="flex items-center gap-1">
                        {batch.moduleTypes.map(type => (
                          <span 
                            key={type}
                            className="px-2 py-0.5 rounded text-xs font-medium"
                            style={{ 
                              fontFamily: "'Roboto Mono', 'Consolas', monospace",
                              backgroundColor: `${colors.skyBlue}20`,
                              color: colors.skyBlue
                            }}
                          >
                            {type}
                          </span>
                        ))}
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>

          {/* Batch Details */}
          <div 
            className="w-1/2"
            style={{ backgroundColor: colors.bgPrimary }}
          >
            {/* Details Header */}
            <div 
              className="px-4 py-2 flex items-center gap-2"
              style={{ 
                backgroundColor: colors.bgElevated,
                borderBottom: `1px solid ${colors.border}`
              }}
            >
              <Hash className="w-4 h-4" style={{ color: colors.electricBlue }} />
              <span 
                className="text-xs uppercase tracking-wider font-semibold"
                style={{ color: colors.electricBlue }}
              >
                Batch Details
              </span>
            </div>

            {/* Details Content */}
            {!selectedBatch ? (
              <div 
                className="p-8 text-center"
                style={{ color: colors.textMuted }}
              >
                <Hash className="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>Select a batch to view details</p>
              </div>
            ) : (
              <div className="p-4">
                {/* Batch Info */}
                <div 
                  className="p-4 rounded-lg mb-4"
                  style={{ 
                    backgroundColor: colors.bgCard,
                    border: `1px solid ${colors.border}`
                  }}
                >
                  <div className="flex items-center justify-between mb-3">
                    <span 
                      className="text-xl font-bold"
                      style={{ color: colors.electricBlue }}
                    >
                      Batch #{selectedBatch.id}
                    </span>
                    <span 
                      className="px-2 py-1 rounded text-xs flex items-center gap-1"
                      style={{ 
                        backgroundColor: `${colors.success}20`,
                        color: colors.success
                      }}
                    >
                      <CheckCircle className="w-3 h-3" />
                      Completed
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <span style={{ color: colors.textMuted }}>Created:</span>
                      <span className="ml-2" style={{ color: colors.textSecondary }}>
                        {formatDate(selectedBatch.createdAt)}
                      </span>
                    </div>
                    <div>
                      <span style={{ color: colors.textMuted }}>Completed:</span>
                      <span className="ml-2" style={{ color: colors.textSecondary }}>
                        {formatDate(selectedBatch.completedAt)}
                      </span>
                    </div>
                    <div>
                      <span style={{ color: colors.textMuted }}>Total Arrays:</span>
                      <span className="ml-2" style={{ color: colors.textSecondary }}>
                        {selectedBatch.totalArrays}
                      </span>
                    </div>
                    <div>
                      <span style={{ color: colors.textMuted }}>Orders:</span>
                      <span className="ml-2" style={{ color: colors.textSecondary }}>
                        {selectedBatch.orderIds.join(', ')}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Module List */}
                <div 
                  className="rounded-lg overflow-hidden"
                  style={{ 
                    backgroundColor: colors.bgCard,
                    border: `1px solid ${colors.border}`
                  }}
                >
                  <div 
                    className="px-4 py-2"
                    style={{ 
                      backgroundColor: colors.bgElevated,
                      borderBottom: `1px solid ${colors.border}`
                    }}
                  >
                    <span 
                      className="text-xs uppercase tracking-wider font-semibold"
                      style={{ color: colors.textSecondary }}
                    >
                      Modules in Batch ({selectedBatch.totalModules})
                    </span>
                  </div>

                  <div style={{ maxHeight: '250px', overflowY: 'auto' }}>
                    {selectedBatch.modules.map((module, index) => (
                      <div 
                        key={index}
                        className="p-3"
                        style={{ 
                          borderBottom: index < selectedBatch.modules.length - 1 
                            ? `1px solid ${colors.border}` 
                            : 'none'
                        }}
                      >
                        <div className="flex items-center justify-between mb-1">
                          <div>
                            <span 
                              className="font-bold text-sm"
                              style={{ 
                                fontFamily: "'Roboto Mono', 'Consolas', monospace",
                                color: colors.skyBlue
                              }}
                            >
                              {module.sku}
                            </span>
                            <span 
                              className="ml-2 text-sm"
                              style={{ color: colors.textSecondary }}
                            >
                              × {module.qty}
                            </span>
                          </div>
                          <div 
                            className="text-xs font-mono px-2 py-1 rounded"
                            style={{ 
                              backgroundColor: colors.bgElevated,
                              color: colors.textMuted
                            }}
                          >
                            {module.serialStart} - {module.serialEnd}
                          </div>
                        </div>
                        <div className="text-xs" style={{ color: colors.textMuted }}>
                          Order #{module.orderId}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="mt-4 flex items-center gap-3">
                  <button
                    onClick={loadBatch}
                    className="flex-1 px-4 py-3 rounded font-bold text-sm flex items-center justify-center gap-2 transition-all"
                    style={{ 
                      backgroundColor: colors.electricBlue,
                      color: '#FFFFFF',
                      boxShadow: `0 0 15px ${colors.electricBlue}30`
                    }}
                    onMouseOver={(e) => {
                      e.currentTarget.style.backgroundColor = colors.skyBlue;
                      e.currentTarget.style.boxShadow = `0 0 25px ${colors.skyBlue}50`;
                    }}
                    onMouseOut={(e) => {
                      e.currentTarget.style.backgroundColor = colors.electricBlue;
                      e.currentTarget.style.boxShadow = `0 0 15px ${colors.electricBlue}30`;
                    }}
                  >
                    <RotateCcw className="w-4 h-4" />
                    Load for Re-engraving
                  </button>
                </div>

                {/* Help Text */}
                <p 
                  className="mt-3 text-xs text-center"
                  style={{ color: colors.textMuted }}
                >
                  Loading this batch will display all modules in the Batch Creator, 
                  where you can select specific modules to re-engrave with new serial numbers.
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Footer Legend */}
        <div 
          className="mt-6 flex items-center justify-between text-xs p-4 rounded-lg"
          style={{ 
            backgroundColor: colors.bgCard,
            border: `1px solid ${colors.border}`,
            color: colors.textMuted
          }}
        >
          <div className="flex items-center gap-4">
            <span className="font-semibold" style={{ color: colors.textSecondary }}>Re-engraving Workflow:</span>
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 rounded" style={{ backgroundColor: colors.bgElevated }}>1</span>
              <span>Select batch</span>
            </div>
            <ChevronRight className="w-4 h-4" />
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 rounded" style={{ backgroundColor: colors.bgElevated }}>2</span>
              <span>Load into Batch Creator</span>
            </div>
            <ChevronRight className="w-4 h-4" />
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 rounded" style={{ backgroundColor: colors.bgElevated }}>3</span>
              <span>Select modules to re-engrave</span>
            </div>
            <ChevronRight className="w-4 h-4" />
            <div className="flex items-center gap-2">
              <span className="px-2 py-0.5 rounded" style={{ backgroundColor: colors.bgElevated }}>4</span>
              <span>New serials assigned</span>
            </div>
          </div>
        </div>

        {/* Brand Footer */}
        <div 
          className="mt-4 text-center text-xs py-2"
          style={{ color: colors.textMuted }}
        >
          LUXEON STAR LEDs by Quadica® • Production Management System
        </div>
      </div>
    </div>
  );
}