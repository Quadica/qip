import React, { useState, useMemo } from 'react';
import { ChevronRight, ChevronDown, Check, Square, Minus, Layers, Package, Hash, Zap, RefreshCw, Clock } from 'lucide-react';

/*
 * Luxeon Star LEDs / Quadica LEDs Brand Guidelines - DARK THEME
 * 
 * Primary Colors (adapted for dark mode):
 * - Deep Navy: #01236d (Dark backgrounds, headers)
 * - Luxeon Blue: #0056A4 (Primary brand elements)
 * - Electric Blue: #00A0E3 (Accents, links, highlights)
 * - Sky Blue: #109cf6 (Light accents on dark)
 * - Pure White: #FFFFFF (Primary text on dark)
 * 
 * Secondary Colors:
 * - Tech Silver: #A8B4BD (Secondary text, borders)
 * - Warm LED: #FFB347 (Warm white product references)
 * - Cool LED: #87CEEB (Cool white product references)
 * - Success Green: #28A745 (Confirmation, success states)
 * - Alert Red: #DC3545 (Warnings, errors)
 * 
 * Dark Theme Backgrounds:
 * - Primary Dark: #0a1628 (Main background)
 * - Card Dark: #0f1f35 (Card backgrounds)
 * - Elevated: #152a45 (Elevated elements)
 * 
 * Typography:
 * - Primary: Roboto, Segoe UI, Arial, sans-serif
 * - Technical/Monospace: Roboto Mono, Consolas, Monaco, monospace
 */

// Mock data simulating query results from oms_batch_items
// WHERE qty_received < build_qty
const mockBatchItems = [
  { id: 1, sku: 'SOLO-34543', order_id: '284534', build_qty: 25, qty_received: 10, customer: 'Acme Lighting' },
  { id: 2, sku: 'SOLO-78291', order_id: '284534', build_qty: 15, qty_received: 0, customer: 'Acme Lighting' },
  { id: 3, sku: 'SOLO-45182', order_id: '284892', build_qty: 50, qty_received: 25, customer: 'TechCorp Industries' },
  { id: 4, sku: 'CORE-91247', order_id: '283721', build_qty: 100, qty_received: 60, customer: 'MediLight Systems' },
  { id: 5, sku: 'CORE-63958', order_id: '283721', build_qty: 75, qty_received: 50, customer: 'MediLight Systems' },
  { id: 6, sku: 'CORE-27461', order_id: '285103', build_qty: 30, qty_received: 0, customer: 'City Hospital' },
  { id: 7, sku: 'EDGE-58324', order_id: '284256', build_qty: 200, qty_received: 150, customer: 'BuildRight Co' },
  { id: 8, sku: 'EDGE-19847', order_id: '284256', build_qty: 100, qty_received: 75, customer: 'BuildRight Co' },
  { id: 9, sku: 'EDGE-72635', order_id: '285412', build_qty: 40, qty_received: 0, customer: 'Workshop Direct' },
  { id: 10, sku: 'STAR-84219', order_id: '284078', build_qty: 60, qty_received: 30, customer: 'HomeGlow LLC' },
  { id: 11, sku: 'STAR-35762', order_id: '284078', build_qty: 45, qty_received: 20, customer: 'HomeGlow LLC' },
  { id: 12, sku: 'STAR-49183', order_id: '285634', build_qty: 80, qty_received: 40, customer: 'Office Solutions Inc' },
];

// Base type definitions for display
const baseTypeNames = {
  'SOLO': 'Star/O',
  'CORE': 'LUXEON C ES',
  'EDGE': 'LUXEON UV U1',
  'STAR': 'Cree XPG'
};

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

export default function EngravingSelector() {
  const [expandedBaseTypes, setExpandedBaseTypes] = useState(new Set());
  const [expandedOrders, setExpandedOrders] = useState(new Set());
  const [selectedModules, setSelectedModules] = useState(new Set());
  const [activeBaseType, setActiveBaseType] = useState(null);
  const [engraveQuantities, setEngraveQuantities] = useState({});

  // Process data into hierarchical structure
  const hierarchicalData = useMemo(() => {
    const structure = {};
    
    mockBatchItems.forEach(item => {
      const baseType = item.sku.substring(0, 4);
      const qtyNeeded = item.build_qty - item.qty_received;
      
      if (!structure[baseType]) {
        structure[baseType] = {
          name: baseTypeNames[baseType] || baseType,
          orders: {}
        };
      }
      
      if (!structure[baseType].orders[item.order_id]) {
        structure[baseType].orders[item.order_id] = {
          customer: item.customer,
          modules: []
        };
      }
      
      structure[baseType].orders[item.order_id].modules.push({
        id: item.id,
        sku: item.sku,
        qtyNeeded,
        buildQty: item.build_qty,
        qtyReceived: item.qty_received
      });
    });
    
    return structure;
  }, []);

  // Get all module IDs for a base type
  const getBaseTypeModuleIds = (baseType) => {
    const ids = new Set();
    Object.values(hierarchicalData[baseType].orders).forEach(order => {
      order.modules.forEach(m => ids.add(m.id));
    });
    return ids;
  };

  // Get all module IDs for an order
  const getOrderModuleIds = (baseType, orderId) => {
    return new Set(hierarchicalData[baseType].orders[orderId].modules.map(m => m.id));
  };

  // Check selection state for a base type
  const getBaseTypeSelectionState = (baseType) => {
    const moduleIds = getBaseTypeModuleIds(baseType);
    const selectedCount = [...moduleIds].filter(id => selectedModules.has(id)).length;
    if (selectedCount === 0) return 'none';
    if (selectedCount === moduleIds.size) return 'all';
    return 'partial';
  };

  // Check selection state for an order
  const getOrderSelectionState = (baseType, orderId) => {
    const moduleIds = getOrderModuleIds(baseType, orderId);
    const selectedCount = [...moduleIds].filter(id => selectedModules.has(id)).length;
    if (selectedCount === 0) return 'none';
    if (selectedCount === moduleIds.size) return 'all';
    return 'partial';
  };

  // Toggle base type expansion
  const toggleBaseTypeExpansion = (baseType) => {
    const newExpanded = new Set(expandedBaseTypes);
    if (newExpanded.has(baseType)) {
      newExpanded.delete(baseType);
    } else {
      newExpanded.add(baseType);
    }
    setExpandedBaseTypes(newExpanded);
  };

  // Toggle order expansion
  const toggleOrderExpansion = (orderId) => {
    const newExpanded = new Set(expandedOrders);
    if (newExpanded.has(orderId)) {
      newExpanded.delete(orderId);
    } else {
      newExpanded.add(orderId);
    }
    setExpandedOrders(newExpanded);
  };

  // Select/deselect base type for engraving (toggles all modules)
  const selectBaseType = (baseType) => {
    const moduleIds = getBaseTypeModuleIds(baseType);
    const currentState = getBaseTypeSelectionState(baseType);
    const newSelected = new Set(selectedModules);
    
    if (currentState === 'all') {
      // If all selected, deselect all modules for this base type
      moduleIds.forEach(id => newSelected.delete(id));
      setActiveBaseType(null);
    } else {
      // Otherwise, select all modules for this base type
      moduleIds.forEach(id => newSelected.add(id));
      setActiveBaseType(baseType);
      
      // Auto-expand
      const newExpandedBase = new Set(expandedBaseTypes);
      newExpandedBase.add(baseType);
      setExpandedBaseTypes(newExpandedBase);
      
      const newExpandedOrders = new Set(expandedOrders);
      Object.keys(hierarchicalData[baseType].orders).forEach(orderId => {
        newExpandedOrders.add(orderId);
      });
      setExpandedOrders(newExpandedOrders);
    }
    
    setSelectedModules(newSelected);
  };

  // Toggle all modules in an order
  const toggleOrderSelection = (baseType, orderId) => {
    const moduleIds = getOrderModuleIds(baseType, orderId);
    const newSelected = new Set(selectedModules);
    const state = getOrderSelectionState(baseType, orderId);
    
    if (state === 'all') {
      moduleIds.forEach(id => newSelected.delete(id));
    } else {
      moduleIds.forEach(id => newSelected.add(id));
    }
    setSelectedModules(newSelected);
  };

  // Toggle individual module
  const toggleModuleSelection = (moduleId) => {
    const newSelected = new Set(selectedModules);
    if (newSelected.has(moduleId)) {
      newSelected.delete(moduleId);
    } else {
      newSelected.add(moduleId);
    }
    setSelectedModules(newSelected);
  };

  // Clear all selections
  const clearAll = () => {
    setSelectedModules(new Set());
    setActiveBaseType(null);
  };

  // Get engrave quantity for a module (defaults to qtyNeeded)
  const getEngraveQty = (moduleId, defaultQty) => {
    return engraveQuantities[moduleId] !== undefined ? engraveQuantities[moduleId] : defaultQty;
  };

  // Update engrave quantity for a module
  const updateEngraveQty = (moduleId, value) => {
    const numValue = parseInt(value) || 0;
    const clampedValue = Math.max(0, numValue);
    setEngraveQuantities(prev => ({
      ...prev,
      [moduleId]: clampedValue
    }));
    
    // Auto-uncheck module if quantity is 0
    if (clampedValue === 0) {
      setSelectedModules(prev => {
        const newSet = new Set(prev);
        newSet.delete(moduleId);
        return newSet;
      });
    }
    // Auto-select module if not already selected and quantity > 0
    else if (!selectedModules.has(moduleId)) {
      setSelectedModules(prev => new Set([...prev, moduleId]));
    }
  };

  // Calculate totals
  const totalModulesSelected = selectedModules.size;
  const totalQtySelected = mockBatchItems
    .filter(item => selectedModules.has(item.id))
    .reduce((sum, item) => {
      const qtyNeeded = item.build_qty - item.qty_received;
      return sum + getEngraveQty(item.id, qtyNeeded);
    }, 0);

  const CheckboxIcon = ({ state }) => {
    if (state === 'all') return <Check className="w-4 h-4" />;
    if (state === 'partial') return <Minus className="w-4 h-4" />;
    return <Square className="w-4 h-4 opacity-40" />;
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
      {/* Header */}
      <div className="max-w-4xl mx-auto">
        {/* Brand Header Bar */}
        <div 
          className="rounded-t-lg px-6 py-4 flex items-center justify-between"
          style={{ 
            background: `linear-gradient(135deg, ${colors.deepNavy} 0%, ${colors.luxeonBlue} 100%)`,
            borderBottom: `1px solid ${colors.electricBlue}`
          }}
        >
          <div className="flex items-center gap-3">
            <div 
              className="w-10 h-10 rounded-lg flex items-center justify-center"
              style={{ 
                backgroundColor: colors.electricBlue,
                boxShadow: `0 0 20px ${colors.electricBlue}40`
              }}
            >
              <Zap className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-white tracking-tight">
                Module Engraving Batch Creator
              </h1>
              <p className="text-sm" style={{ color: colors.skyBlue }}>
                LUXEON STAR LEDs Production System
              </p>
            </div>
          </div>
          <div className="flex flex-col items-end gap-2">
            <p className="text-xs" style={{ color: colors.coolLed }}>Quadica Developments Inc.</p>
            <button
              onClick={() => {
                // TODO: Navigate to Batch History page
                console.log('Navigate to Batch History');
              }}
              className="flex items-center gap-1 text-xs px-3 py-1 rounded transition-all"
              style={{ 
                backgroundColor: 'rgba(255,255,255,0.1)',
                color: colors.skyBlue
              }}
              onMouseOver={(e) => {
                e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.2)';
                e.currentTarget.style.color = colors.electricBlue;
              }}
              onMouseOut={(e) => {
                e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.1)';
                e.currentTarget.style.color = colors.skyBlue;
              }}
              title="View previously completed batches for re-engraving"
            >
              <Clock className="w-3 h-3" />
              View Batch History
            </button>
          </div>
        </div>

        {/* Stats Bar */}
        <div 
          className="grid grid-cols-3"
          style={{ 
            backgroundColor: colors.bgCard,
            borderLeft: `1px solid ${colors.border}`,
            borderRight: `1px solid ${colors.border}`
          }}
        >
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.border}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Base Types</div>
            <div className="text-3xl font-bold" style={{ color: colors.skyBlue }}>{Object.keys(hierarchicalData).length}</div>
          </div>
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.border}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Selected SKUs</div>
            <div className="text-3xl font-bold" style={{ color: colors.electricBlue }}>{totalModulesSelected}</div>
          </div>
          <div className="p-4 text-center">
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Total Units</div>
            <div className="text-3xl font-bold" style={{ color: colors.success }}>{totalQtySelected}</div>
          </div>
        </div>

        {/* Hierarchical List */}
        <div 
          className="rounded-b-lg overflow-hidden"
          style={{ 
            border: `1px solid ${colors.border}`
          }}
        >
          {/* Conditional Header: Shows action bar when modules selected, otherwise shows "Modules Awaiting Engraving" */}
          {totalModulesSelected > 0 ? (
            <div 
              className="px-4 flex items-center justify-between"
              style={{ 
                backgroundColor: `${colors.electricBlue}15`,
                borderBottom: `1px solid ${colors.electricBlue}30`,
                height: '56px'
              }}
            >
              <div className="flex items-center gap-3">
                <div 
                  className="w-8 h-8 rounded-full flex items-center justify-center"
                  style={{ 
                    backgroundColor: colors.electricBlue,
                    boxShadow: `0 0 15px ${colors.electricBlue}50`
                  }}
                >
                  <Zap className="w-4 h-4 text-white" />
                </div>
                <span className="font-semibold" style={{ color: colors.skyBlue }}>
                  {totalModulesSelected} module{totalModulesSelected !== 1 ? 's' : ''} ready for engraving ({totalQtySelected} units)
                </span>
              </div>
              <div className="flex gap-2">
                <button 
                  onClick={clearAll}
                  className="px-4 py-2 text-sm rounded transition-all"
                  style={{ 
                    border: `1px solid ${colors.border}`,
                    color: colors.textSecondary,
                    backgroundColor: 'transparent'
                  }}
                  onMouseOver={(e) => {
                    e.target.style.backgroundColor = colors.bgElevated;
                    e.target.style.borderColor = colors.borderLight;
                  }}
                  onMouseOut={(e) => {
                    e.target.style.backgroundColor = 'transparent';
                    e.target.style.borderColor = colors.border;
                  }}
                >
                  Clear Selection
                </button>
                <button 
                  className="px-4 py-2 text-sm font-bold rounded transition-all text-white"
                  style={{ 
                    backgroundColor: colors.electricBlue,
                    boxShadow: `0 0 15px ${colors.electricBlue}30`
                  }}
                  onMouseOver={(e) => {
                    e.target.style.backgroundColor = colors.skyBlue;
                    e.target.style.boxShadow = `0 0 25px ${colors.skyBlue}50`;
                  }}
                  onMouseOut={(e) => {
                    e.target.style.backgroundColor = colors.electricBlue;
                    e.target.style.boxShadow = `0 0 15px ${colors.electricBlue}30`;
                  }}
                >
                  Start Engraving →
                </button>
              </div>
            </div>
          ) : (
            <div 
              className="px-4 flex items-center gap-2"
              style={{ 
                backgroundColor: colors.bgElevated,
                borderBottom: `1px solid ${colors.border}`,
                height: '56px'
              }}
            >
              <Layers className="w-4 h-4" style={{ color: colors.electricBlue }} />
              <span 
                className="text-xs uppercase tracking-wider font-semibold flex-1"
                style={{ color: colors.electricBlue }}
              >
                Modules Awaiting Engraving
              </span>
              <button
                onClick={() => {
                  // TODO: Implement WordPress DB query to refresh module list
                  console.log('Refresh module list');
                }}
                className="p-2 rounded transition-all"
                style={{ color: colors.textMuted }}
                onMouseOver={(e) => {
                  e.currentTarget.style.backgroundColor = colors.bgHover;
                  e.currentTarget.style.color = colors.electricBlue;
                }}
                onMouseOut={(e) => {
                  e.currentTarget.style.backgroundColor = 'transparent';
                  e.currentTarget.style.color = colors.textMuted;
                }}
                title="Refresh module list"
              >
                <RefreshCw className="w-4 h-4" />
              </button>
            </div>
          )}
          
          <div style={{ backgroundColor: colors.bgCard }}>
            {Object.entries(hierarchicalData).map(([baseType, baseData], index) => {
              const isBaseExpanded = expandedBaseTypes.has(baseType);
              const baseSelectionState = getBaseTypeSelectionState(baseType);
              const isActive = activeBaseType === baseType;
              const totalBaseQty = Object.values(baseData.orders)
                .flatMap(o => o.modules)
                .reduce((sum, m) => sum + m.qtyNeeded, 0);
              const orderCount = Object.keys(baseData.orders).length;
              const isLast = index === Object.keys(hierarchicalData).length - 1;
              
              return (
                <div 
                  key={baseType} 
                  style={{ 
                    backgroundColor: isActive ? `${colors.electricBlue}10` : 'transparent',
                    borderBottom: isLast ? 'none' : `1px solid ${colors.border}`
                  }}
                >
                  {/* Base Type Row */}
                  <div 
                    className="flex items-center gap-2 px-4 py-3 transition-colors cursor-pointer"
                    onMouseOver={(e) => e.currentTarget.style.backgroundColor = isActive ? `${colors.electricBlue}15` : colors.bgElevated}
                    onMouseOut={(e) => e.currentTarget.style.backgroundColor = isActive ? `${colors.electricBlue}10` : 'transparent'}
                  >
                    <button 
                      onClick={() => toggleBaseTypeExpansion(baseType)}
                      className="p-1 rounded transition-colors"
                      style={{ color: colors.textMuted }}
                    >
                      {isBaseExpanded ? (
                        <ChevronDown className="w-5 h-5" />
                      ) : (
                        <ChevronRight className="w-5 h-5" />
                      )}
                    </button>
                    
                    <button
                      onClick={() => selectBaseType(baseType)}
                      className="w-6 h-6 rounded flex items-center justify-center transition-all"
                      style={{
                        backgroundColor: baseSelectionState === 'all' ? colors.electricBlue :
                                        baseSelectionState === 'partial' ? colors.skyBlue : colors.bgElevated,
                        color: baseSelectionState !== 'none' ? '#FFFFFF' : colors.textMuted,
                        boxShadow: baseSelectionState !== 'none' ? `0 0 10px ${colors.electricBlue}40` : 'none'
                      }}
                    >
                      <CheckboxIcon state={baseSelectionState} />
                    </button>
                    
                    <div className="flex-1 flex items-center gap-3">
                      <span 
                        className="font-bold text-sm px-2 py-0.5 rounded"
                        style={{ 
                          fontFamily: "'Roboto Mono', 'Consolas', monospace",
                          color: colors.skyBlue,
                          backgroundColor: `${colors.skyBlue}20`
                        }}
                      >
                        {baseType}
                      </span>
                      <span style={{ color: colors.textPrimary }}>{baseData.name}</span>
                    </div>
                    
                    <div className="flex items-center gap-4 text-sm">
                      <span style={{ color: colors.textMuted }}>
                        {orderCount} order{orderCount !== 1 ? 's' : ''}
                      </span>
                      <span 
                        className="px-2 py-1 rounded text-sm font-medium"
                        style={{ 
                          backgroundColor: colors.bgElevated,
                          color: colors.textSecondary
                        }}
                      >
                        {totalBaseQty} units
                      </span>
                    </div>
                    
                  </div>
                  
                  {/* Orders under Base Type */}
                  {isBaseExpanded && (
                    <div style={{ backgroundColor: colors.bgPrimary }}>
                      {Object.entries(baseData.orders).map(([orderId, orderData]) => {
                        const isOrderExpanded = expandedOrders.has(orderId);
                        const orderSelectionState = getOrderSelectionState(baseType, orderId);
                        const orderQty = orderData.modules.reduce((sum, m) => sum + m.qtyNeeded, 0);
                        
                        return (
                          <div key={orderId}>
                            {/* Order Row */}
                            <div 
                              className="flex items-center gap-2 px-4 py-2 pl-12 transition-colors"
                              style={{ 
                                borderLeft: `3px solid ${colors.electricBlue}`,
                                marginLeft: '16px',
                                backgroundColor: colors.bgPrimary
                              }}
                              onMouseOver={(e) => e.currentTarget.style.backgroundColor = colors.bgCard}
                              onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.bgPrimary}
                            >
                              <button 
                                onClick={() => toggleOrderExpansion(orderId)}
                                className="p-1 rounded"
                                style={{ color: colors.textMuted }}
                              >
                                {isOrderExpanded ? (
                                  <ChevronDown className="w-4 h-4" />
                                ) : (
                                  <ChevronRight className="w-4 h-4" />
                                )}
                              </button>
                              
                              <button
                                onClick={() => toggleOrderSelection(baseType, orderId)}
                                className="w-5 h-5 rounded flex items-center justify-center transition-all"
                                style={{
                                  backgroundColor: orderSelectionState === 'all' ? colors.success :
                                                  orderSelectionState === 'partial' ? colors.coolLed : colors.bgElevated,
                                  color: orderSelectionState !== 'none' ? '#FFFFFF' : colors.textMuted,
                                  boxShadow: orderSelectionState === 'all' ? `0 0 8px ${colors.success}50` : 'none'
                                }}
                              >
                                <CheckboxIcon state={orderSelectionState} />
                              </button>
                              
                              <Package className="w-4 h-4" style={{ color: colors.textMuted }} />
                              
                              <div className="flex-1 flex items-center gap-3">
                                <span 
                                  className="font-medium text-sm"
                                  style={{ color: colors.coolLed }}
                                >
                                  {orderId}
                                </span>
                                <span className="text-sm" style={{ color: colors.textMuted }}>
                                  {orderData.customer}
                                </span>
                              </div>
                              
                              <div className="flex items-center gap-3 text-xs">
                                <span style={{ color: colors.textMuted }}>
                                  {orderData.modules.length} SKU{orderData.modules.length !== 1 ? 's' : ''}
                                </span>
                                <span 
                                  className="px-2 py-0.5 rounded"
                                  style={{ 
                                    backgroundColor: colors.bgElevated,
                                    color: colors.textSecondary
                                  }}
                                >
                                  {orderQty} units
                                </span>
                              </div>
                            </div>
                            
                            {/* Modules under Order */}
                            {isOrderExpanded && (
                              <div>
                                {orderData.modules.map(module => {
                                  const isSelected = selectedModules.has(module.id);
                                  
                                  return (
                                    <div 
                                      key={module.id}
                                      className="flex items-center gap-2 px-4 py-2 pl-20 transition-colors"
                                      style={{ 
                                        borderLeft: `3px solid ${colors.skyBlue}50`,
                                        marginLeft: '32px',
                                        backgroundColor: colors.bgPrimary
                                      }}
                                      onMouseOver={(e) => e.currentTarget.style.backgroundColor = colors.bgCard}
                                      onMouseOut={(e) => e.currentTarget.style.backgroundColor = colors.bgPrimary}
                                    >
                                      <button
                                        onClick={() => toggleModuleSelection(module.id)}
                                        className="w-4 h-4 rounded flex items-center justify-center transition-all"
                                        style={{
                                          backgroundColor: isSelected ? colors.skyBlue : colors.bgElevated,
                                          color: isSelected ? '#FFFFFF' : colors.textMuted,
                                          boxShadow: isSelected ? `0 0 8px ${colors.skyBlue}50` : 'none'
                                        }}
                                      >
                                        {isSelected && <Check className="w-3 h-3" />}
                                      </button>
                                      
                                      <Hash className="w-3 h-3" style={{ color: colors.textMuted }} />
                                      
                                      <div className="flex-1">
                                        <span 
                                          className="text-sm"
                                          style={{ 
                                            fontFamily: "'Roboto Mono', 'Consolas', monospace",
                                            color: isSelected ? colors.skyBlue : colors.textSecondary
                                          }}
                                        >
                                          {module.sku}
                                        </span>
                                      </div>
                                      
                                      <div className="flex items-center gap-4 text-xs">
                                        <span style={{ color: colors.textMuted }}>
                                          {module.qtyReceived}/{module.buildQty} complete
                                        </span>
                                        <div className="flex items-center gap-1">
                                          <input
                                            type="number"
                                            min="0"
                                            value={getEngraveQty(module.id, module.qtyNeeded)}
                                            onChange={(e) => updateEngraveQty(module.id, e.target.value)}
                                            className="w-16 px-2 py-0.5 rounded text-center text-xs font-bold"
                                            style={{
                                              backgroundColor: isSelected 
                                                ? `${colors.skyBlue}25` 
                                                : colors.bgElevated,
                                              color: isSelected ? colors.skyBlue : colors.textMuted,
                                              border: `1px solid ${isSelected ? colors.skyBlue : colors.border}`,
                                              outline: 'none'
                                            }}
                                            onFocus={(e) => {
                                              e.target.style.borderColor = colors.electricBlue;
                                              e.target.style.boxShadow = `0 0 0 2px ${colors.electricBlue}30`;
                                            }}
                                            onBlur={(e) => {
                                              e.target.style.borderColor = isSelected ? colors.skyBlue : colors.border;
                                              e.target.style.boxShadow = 'none';
                                            }}
                                          />
                                          <span style={{ color: colors.textMuted }}>
                                            / {module.qtyNeeded}
                                          </span>
                                        </div>
                                      </div>
                                    </div>
                                  );
                                })}
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        {/* Footer Legend */}
        <div 
          className="mt-6 flex items-center gap-6 text-xs p-4 rounded-lg"
          style={{ 
            backgroundColor: colors.bgCard,
            border: `1px solid ${colors.border}`,
            color: colors.textMuted
          }}
        >
          <div className="flex items-center gap-2">
            <div 
              className="w-3 h-3 rounded" 
              style={{ 
                backgroundColor: colors.electricBlue,
                boxShadow: `0 0 6px ${colors.electricBlue}50`
              }}
            ></div>
            <span>Base Type Selected</span>
          </div>
          <div className="flex items-center gap-2">
            <div 
              className="w-3 h-3 rounded" 
              style={{ 
                backgroundColor: colors.success,
                boxShadow: `0 0 6px ${colors.success}50`
              }}
            ></div>
            <span>Order Selected</span>
          </div>
          <div className="flex items-center gap-2">
            <div 
              className="w-3 h-3 rounded" 
              style={{ 
                backgroundColor: colors.skyBlue,
                boxShadow: `0 0 6px ${colors.skyBlue}50`
              }}
            ></div>
            <span>Module Enabled</span>
          </div>
          <div className="flex items-center gap-2">
            <div 
              className="w-3 h-3 rounded" 
              style={{ backgroundColor: colors.bgElevated }}
            ></div>
            <span>Disabled</span>
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